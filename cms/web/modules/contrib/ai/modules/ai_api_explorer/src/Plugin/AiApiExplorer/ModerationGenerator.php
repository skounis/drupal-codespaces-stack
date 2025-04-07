<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'moderation_generator',
  title: new TranslatableMarkup('Moderation Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI moderation tool with prompts.'),
)]
final class ModerationGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('moderation');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'ai-text-response');

    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'moderation', 'moderation', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['moderation_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask The AI'),
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'moderation', 'moderation');

    $response = $provider->moderation($form_state->getValue('prompt'), $form_state->getValue('moderation_ai_model'), ['moderation_generation'])->getNormalized();

    if (get_class($response) == ModerationResponse::class) {
      $form['right']['response']['#context']['ai_response']['response'] = [
        'flag' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => t('Got flagged: :result', [
            ':result' => $response->isFlagged() ? 'Yes' : 'No',
          ]),
        ],
        'dump' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Information dump:<pre>:dump</pre>', [
            ':dump' => print_r($response->getInformation(), TRUE),
          ]),
        ],
      ];

      $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($form_state, $form_state->getValue('prompt'));
    }
    else {
      $form['right']['response']['#context']['ai_response']['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Error: Invalid response from the provider.'),
      ];
    }

    return $form['right'];
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $prompt
   *   The prompt.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(FormStateInterface $form_state, string $prompt): array {
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('moderation_ai_provider') . '\');<br>';
    $code['code']['#value'] .= "// Normalized \$response will be a ModerationResponse object.<br>";
    $code['code']['#value'] .= "\$prompt = '" . $prompt . "';<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->moderation(\$input, '" . $form_state->getValue('moderation_ai_model') . '\', ["your_module_name"])->getNormalized();';

    return $code;
  }

}
