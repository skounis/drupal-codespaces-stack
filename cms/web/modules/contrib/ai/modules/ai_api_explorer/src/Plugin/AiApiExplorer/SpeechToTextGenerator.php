<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'speech_to_text_generator',
  title: new TranslatableMarkup('Speech-To-Text Generation Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI speech-to-text generator with prompts.'),
)]
final class SpeechToTextGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('speech_to_text');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('stt_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('stt_ai_model', $request->query->get('model_id'));
    }

    $form = $this->getFormTemplate($form, 'ai-text-response');

    $form['left']['file'] = [
      '#type' => 'file',
      // Only mp3 files are allowed in this case, since that covers most models.
      '#accept' => '.mp3',
      '#title' => $this->t('Upload your file here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'speech_to_text', 'stt', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['stt_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate a Text'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-text-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'speech_to_text', 'stt');
    if ($audio_file = $this->generateFile()) {
      $raw_file = new SpeechToTextInput($audio_file);

      try {
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $provider->speechToText($raw_file, $form_state->getValue('stt_ai_model'), ['ai_api_explorer'])
            ->getNormalized(),
        ];
        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $audio_file->getFilename());
      }
      catch (\Exception $e) {
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'inline_template',
          '#template' => '{{ error|raw }}',
          '#context' => [
            'error' => $this->explorerHelper->renderException($e),
          ],
        ];
      }
    }

    return $form['right'];
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $filename
   *   The filename.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $filename): array {
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '$audio = file_get_contents("' . $filename . '");<br>';
    $code['code']['#value'] .= $this->addProviderCodeExample($provider);

    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('stt_ai_provider') . '\');<br>';
    $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code['code']['#value'] .= "// \$response will be a string with the text.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->speechToText(\$audio, '" . $form_state->getValue('stt_ai_model') . '\', ["your_module_name"]);';

    return $code;
  }

}
