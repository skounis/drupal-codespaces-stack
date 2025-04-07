<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Flag the form state to rebuild the form again after submission.
 *
 * @Action(
 *   id = "eca_form_state_set_rebuild",
 *   label = @Translation("Form state: set rebuild"),
 *   description = @Translation("Flag the form state to rebuild the form again after submission."),
 *   eca_version_introduced = "1.1.0",
 *   type = "form"
 * )
 */
class FormStateSetRebuild extends FormActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }
    $rebuild = (bool) $this->configuration['rebuild'];
    $form_state->setRebuild($rebuild);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rebuild' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['rebuild'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable rebuild'),
      '#description' => $this->t('When enabled, the form state will be flagged to rebuild. If not, the form state will be flagged to not rebuild.'),
      '#default_value' => $this->configuration['rebuild'],
      '#weight' => -49,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['rebuild'] = !empty($form_state->getValue('rebuild'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
