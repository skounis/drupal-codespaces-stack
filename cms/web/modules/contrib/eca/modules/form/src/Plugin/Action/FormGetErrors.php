<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Get currently existing errors of a form.
 *
 * @Action(
 *   id = "eca_form_get_errors",
 *   label = @Translation("Form: get errors"),
 *   description = @Translation("Makes form errors available as token."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormGetErrors extends FormActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }
    $errors = $form_state->getErrors();
    array_walk_recursive($errors, function (&$message) {
      if (is_object($message) && method_exists($message, '__toString')) {
        $message = (string) $message;
      }
    });

    $this->tokenService->addTokenData($this->configuration['token_name'], $errors);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The token will hold the list of existing form errors, keyed by form element. For example, when token name is defines as "<em>errors</em>", then a specific element may be accessed with <em>[errors:&lt;field_name&gt;]</em>.'),
      '#default_value' => $this->configuration['token_name'],
      '#required' => TRUE,
      '#weight' => -49,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
