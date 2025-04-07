<?php

namespace Drupal\ai_ckeditor\Plugin\AICKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;

/**
 * Plugin to do AI completion.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_completion',
  label: new TranslatableMarkup('Generate with AI'),
  description: new TranslatableMarkup('Get ideas and text completion assistance from AI.'),
)]
final class Completion extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    array_shift($options);
    array_splice($options, 0, 1);
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_complete = $prompts_config->get('prompts.complete');
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Completion pre prompt'),
      '#default_value' => $prompt_complete ?? '',
      '#description' => $this->t('This prompt will be prepended before the user prompt. This field may be left empty too.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.complete', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array {
    $form = parent::buildCkEditorModalForm($form, $form_state);

    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');

    $form['text_to_submit'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What would you like to ask or get ideas for?'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    $form['response_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Response from AI'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
      '#prefix' => '<div id="ai-ckeditor-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
      '#ai_ckeditor_response' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxGenerate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();
    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_complete = $prompts_config->get('prompts.complete');
    if (!empty($prompt_complete)) {
      $prompt = $prompt_complete . PHP_EOL . $values["plugin_config"]["text_to_submit"];
    }
    else {
      $prompt = $values["plugin_config"]["text_to_submit"];
    }
    $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));

    return $response;
  }

}
