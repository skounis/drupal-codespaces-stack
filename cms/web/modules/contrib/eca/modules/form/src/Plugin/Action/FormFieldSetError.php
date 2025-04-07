<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Action to show a validation error message.
 *
 * @Action(
 *   id = "eca_form_field_set_error",
 *   label = @Translation("Form field: set validation error"),
 *   description = @Translation("Shows a validation error with a given message text."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldSetError extends FormFieldValidateActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'message' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#description' => $this->t('The error message to be shown regards the form field.'),
      '#default_value' => $this->configuration['message'],
      '#weight' => -49,
      '#eca_token_replacement' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['field_name']['#description'] .= ' ' . $this->t("Leave empty to set a global error on the form.");
    $form['field_name']['#required'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (trim((string) $this->configuration['field_name']) === '') {
      $result = AccessResult::allowed();
      return $return_as_object ? $result : $result->isAllowed();
    }
    return parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (trim((string) $this->configuration['field_name']) === '') {
      // We support setting a global error on the whole form.
      $this->doExecute();
    }
    else {
      // Otherwise, let the parent logic execute.
      parent::execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $this->setError($this->tokenService->replaceClear($this->configuration['message']));
  }

}
