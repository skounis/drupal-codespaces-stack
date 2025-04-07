<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'chat_generator',
  title: new TranslatableMarkup('Chat Generation Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI chat generator with prompts.'),
)]
final class ChatGenerator extends AiApiExplorerPluginBase {

  /**
   * Constructs the base plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\ai\Service\AiProviderFormHelper $aiProviderHelper
   *   The AI Provider Helper.
   * @param \Drupal\ai_api_explorer\ExplorerHelper $explorerHelper
   *   The Explorer helper.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The Provider Manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Renderer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, AiProviderFormHelper $aiProviderHelper, ExplorerHelper $explorerHelper, AiProviderPluginManager $providerManager, protected Renderer $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $aiProviderHelper, $explorerHelper, $providerManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('ai.form_helper'),
      $container->get('ai_api_explorer.helper'),
      $container->get('ai.provider'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'ai-text-response', 'three-column');
    $form['#attached']['library'][] = 'ai_api_explorer/stream';

    $form['left']['prompts'] = [
      '#type' => 'details',
      '#title' => $this->t('Chat Messages'),
      '#open' => TRUE,
      '#description' => $this->t('<strong>Please note: This is not a chat, its an explorer of the chat endpoint to build chat logic!</strong> <br />Enter your chat messages here, each message has to have a role and a message. Role will no always be used by all providers/models.'),
    ];

    $form['left']['prompts']['system_prompt'] = [
      '#type' => 'details',
      '#title' => $this->t('System Prompt'),
      '#open' => FALSE,
    ];

    $form['left']['prompts']['system_prompt']['system_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Message'),
      '#attributes' => [
        'placeholder' => $this->t('You are an helpful assistant.'),
      ],
      '#required' => FALSE,
    ];

    $form['left']['prompts']['role_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role'),
      '#attributes' => [
        'placeholder' => $this->t('user, system, assistant, etc.'),
      ],
      '#default_value' => 'user',
      '#required' => TRUE,
    ];
    $form['left']['prompts']['message_1'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#attributes' => [
        'placeholder' => $this->t('Write you message here.'),
      ],
      '#required' => TRUE,
      '#default_value' => '',
    ];
    $form['left']['prompts']['image_1'] = [
      '#type' => 'file',
      // Only jpg, png files are allowed, since that covers most models.
      '#accept' => '.jpg, .png, .jpeg',
      '#title' => $this->t('Image'),
      '#description' => $this->t('Attach an image to the call. Note that not all models support images and will throw an error.'),
    ];

    $form['left']['streamed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Streamed'),
      '#description' => $this->t('If the provider supports streaming, the response will be streamed. <strong>Currently the image chat will not work with streaming in this explorer (however in the API it works).</strong>'),
    ];

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask The AI'),
      '#attributes' => [
        'data-response' => 'ai-text-response',
      ],
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-text-response',
      ],
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['right'], $form_state, 'chat', 'chat', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 1003);

    $form['right']['chat_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    // This runs on streamed.
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'chat');
    $values = $form_state->getValues();
    // Get the messages.
    $messages = [];
    // Get potential files.
    $files = $this->getRequest()->files->all();
    foreach ($values as $key => $value) {
      if (str_starts_with($key, 'role_')) {
        $index = substr($key, 5);
        $role = $value;
        $message = $values['message_' . $index];
        // Load the file.
        $image = "";
        if (isset($files['files']['image_' . $index])) {
          $raw_file = file_get_contents($files['files']['image_' . $index]->getPathname());
          $image = new ImageFile($raw_file, $files['files']['image_' . $index]->getClientMimeType(), $files['files']['image_' . $index]->getClientOriginalName());
        }
        if ($role && $message) {
          $images = [];
          if ($image) {
            $images[] = $image;
          }
          $messages[] = new ChatMessage($role, $message, $images);
        }
      }
    }

    $input = new ChatInput($messages);

    // Check for system message.
    if ($form_state->getValue('system_message')) {
      $provider->setChatSystemRole($form_state->getValue('system_message'));
    }

    $message = NULL;
    $response = NULL;
    try {
      // If we should stream.
      if ($form_state->getValue('streamed')) {
        $provider->streamedOutput();
      }
      $response = $provider->chat($input, $form_state->getValue('chat_ai_model'), [
        'chat_generation',
        'ai_api_explorer',
      ])->getNormalized();
    }
    catch (\Exception $e) {
      $message = $this->explorerHelper->renderException($e);
    }

    // Generation code for normalization.
    $code = $this->normalizeCodeExample($provider, $form_state, $messages);

    if (is_object($response) && get_class($response) == ChatMessage::class) {
      $form['middle']['response']['#context']['ai_response']['role'] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => 'Role: ' . $response->getRole(),
      ];
      $form['middle']['response']['#context']['ai_response']['text'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $response->getText(),
      ];
      $form['middle']['response']['#context']['ai_response']['code'] = $code;
      $form_state->setRebuild();
      return $form['middle'];
    }
    elseif ($response instanceof StreamedChatMessageIteratorInterface) {
      $http_response = new StreamedResponse();
      $http_response->setCallback(function () use ($response, $code) {
        foreach ($response as $key => $chat_message) {
          if ($chat_message->getRole() && !$key) {
            echo '<h4>Role: ' . $chat_message->getRole() . "</h4><p>";
          }
          echo $chat_message->getText();
          ob_flush();
          flush();
        }
        echo $this->renderer->render($code);
        ob_flush();
        flush();
      });
      $form_state->setResponse($http_response);
    }
    else {
      $form['middle']['response']['#context']['ai_response']['#markup'] = $message;
      $form_state->setRebuild();
      return $form['middle'];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->getResponse($form, $form_state);
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $messages
   *   The messages.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, array $messages): array {
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '// Use this when you want to be able to swap the provider. <br>';
    $show_config = count($provider->getConfiguration());

    if ($show_config) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }

    $code['code']['#value'] .= '$input = new \Drupal\ai\OperationType\Chat\ChatInput([<br>';

    foreach ($messages as $message) {
      if (count($message->getImages())) {
        $code['code']['#value'] .= '&nbsp;&nbsp;// Assume a File entity being used with variable $file.<br>';
        $code['code']['#value'] .= '&nbsp;&nbsp;$image = new \Drupal\ai\OperationType\GenericType\ImageFile();<br>';
        $code['code']['#value'] .= '&nbsp;&nbsp;$image->setFileFromFile($file);<br>';
        $code['code']['#value'] .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getText() . '", $image),<br>';
      }
      else {
        $code['code']['#value'] .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getText() . '"),<br>';
      }
    }
    $code['code']['#value'] .= ']);<br><br>';

    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    if ($show_config) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    if ($form_state->getValue('system_message')) {
      $code['code']['#value'] .= '$ai_provider->setChatSystemRole("' . $form_state->getValue('system_message') . '");<br>';
    }
    if ($form_state->getValue('streamed')) {
      $code['code']['#value'] .= "// If you want to stream the response normalized you have to make sure<br>";
      $code['code']['#value'] .= "\$ai_provider->streamedOutput();<br>";
    }
    $code['code']['#value'] .= "// Normalized \$response will be a ChatMessage object.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->chat(\$input, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getNormalized();<br>';

    // If there is a streaming response.
    if ($form_state->getValue('streamed')) {
      $code['code']['#value'] .= "<br><br>// If you want to stream the response normalized you have to make sure<br>";
      $code['code']['#value'] .= "// the provider supports it and have a fallback if not. This shows how. <br><br>";
      $code['code']['#value'] .= "// It is a stream response.<br>";
      $code['code']['#value'] .= "if (\$response instanceof \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface) {<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;// This is a stream response.<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;// You can loop through the response and output it as it comes in.<br>";
      $code['code']['#value'] .= "/* @var \Drupal\ai\OperationType\Chat\StreamedChatMessage \$chat_message */<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;foreach (\$response as \$chat_message) {<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;&nbsp;&nbsp;echo \$chat_message->getText();<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;}<br>";
      $code['code']['#value'] .= "} else {<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;// This is a normal response.<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;echo \$response->getText();<br>";
      $code['code']['#value'] .= "}<br>";
    }

    return [
      'code' => $code,
      'raw_code' => $this->rawCodeExample($provider, $form_state),
    ];
  }

  /**
   * Gets the raw code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The raw code example.
   */
  public function rawCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state): array {
    $code = $this->getCodeExampleTemplate();
    $code['#title'] = $this->t('Raw Code Example');

    $code['code']['#value'] .= '// Another way if you know you always will use ' . $provider->getPluginDefinition()['label'] . ' and want its way of doing stuff. Not recommended. <br>';
    $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    if ($form_state->getValue('system_message')) {
      $code['code']['#value'] .= '$ai_provider->setChatSystemRole("' . $form_state->getValue('system_message') . '");<br>';
    }
    $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code['code']['#value'] .= "// Normalized \$response will be what ever the provider gives back.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->chat(\$expectedInputFromProviderClient, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getRawOutput();';

    return $code;
  }

  /**
   * Ajax callback accounting for the different form structure.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The correct section of the form.
   *
   * @see \Drupal\ai\Service\AiProviderFormHelper::loadModelsAjaxCallback
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state): mixed {
    $prefix = $form_state->getTriggeringElement()['#ajax']['data-prefix'];
    $form_state->setRebuild();
    return $form['right'][$prefix . 'ajax_prefix'];
  }

}
