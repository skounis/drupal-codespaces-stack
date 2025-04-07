<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 *
 * @AiApiExplorer(
 *   id = "embeddings_generator",
 *   label = @Translation("Embeddings Generation Explorer"),
 *   description = @Translation("Contains a form where you can experiment and test the AI embeddings generator with prompts.")
 * )
 */
#[AiApiExplorer(
  id: 'embeddings_generator',
  title: new TranslatableMarkup('Embeddings Generation Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI embeddings generator with prompts.'),
)]
final class EmbeddingsGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('embeddings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('tts_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('tts_ai_model', $request->query->get('model_id'));
    }
    $input = Json::decode($request->query->get('input', '[]'));

    $form = $this->getFormTemplate($form, 'ai-embeddings-response');

    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
    ];

    $form['left']['image'] = [
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('(OR) upload an image here if its an image embeddings model. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'embeddings', 'embed', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['embed_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Embeddings'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-embeddings-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'embeddings', 'embed');

    // Normalize the input.
    $input = new EmbeddingsInput();
    if ($file = $this->generateFile('image')) {
      $file_name = $file->getFilename();

      // Because its octect/stream sometimes.
      $file->resetMimeTypeFromFileName();
      $input->setImage($file);
    }
    else {
      $input->setPrompt($form_state->getValue('prompt'));
      $file_name = NULL;
    }
    try {
      $embeddings = $provider->embeddings($input, $form_state->getValue('embed_ai_model'), ['ai_api_explorer']);
      $response = implode(', ', $embeddings->getNormalized());
    }
    catch (\Exception $e) {
      $form['right']['response']['#context']['ai_response']['response'] = [
        '#type' => 'inline_template',
        '#template' => '{{ error|raw }}',
        '#context' => [
          'error' => $this->explorerHelper->renderException($e),
        ],
      ];

      // Early return if we've hit an error.
      return $form['right'];
    }

    $form['right']['response']['#context']['ai_response']['response'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $response,
    ];
    $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $form_state->getValue('prompt'), $file_name);

    return $form['right'];
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $prompt
   *   The prompt.
   * @param string|null $filename
   *   The filename.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $prompt = "", ?string $filename = NULL): array {
    $code = $this->getCodeExampleTemplate();
    if ($filename) {
      $code['code']['#value'] .= '$binary = file_get_contents("' . $filename . '");<br>';
    }
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }

    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('embed_ai_provider') . '\');<br>';
    $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$input = new \Drupal\ai\OperationType\Embeddings\EmbeddingsInput();<br>";
    if ($filename) {
      $code['code']['#value'] .= "\$image_file = new \Drupal\ai\OperationType\GenericType\ImageFile(\$binary, 'image/jpg', '" . $filename . "');<br>";
      $code['code']['#value'] .= "\$input->setImage(\$image_file);<br>";
    }
    else {
      $code['code']['#value'] .= "\$input->setPrompt('" . $prompt . "');<br>";
    }
    $code['code']['#value'] .= "\$response = \$ai_provider->embeddings(\$input, '" . $form_state->getValue('embed_ai_model') . '\', ["your_module_name"]);<br><br>';
    $code['code']['#value'] .= "// This gets an array of vector numbers (unless other output is possible).<br>";
    $code['code']['#value'] .= "\$normalized = \$response->getNormalized();<br><br>";
    $code['code']['#value'] .= "// Another possibility is to get the raw response from the provider.<br>";
    $code['code']['#value'] .= '$raw = $response->getRaw();<br>';

    return $code;
  }

}
