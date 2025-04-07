<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Evaluates against the trigger name of the submission.
 *
 * @EcaCondition(
 *   id = "eca_form_triggered",
 *   label = @Translation("Form: compare triggered submission"),
 *   description = @Translation("Evaluates against the trigger name of the submission."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class FormTriggered extends StringComparisonBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   *
   * The left value does not make sense to run through Token replacement.
   */
  protected static bool $replaceTokens = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    if (!($form_state = $this->getCurrentFormState())) {
      // Since the StringComparisonBase always compares string values, we want
      // to make sure, that the evaluation will return FALSE when there is no
      // current form state available.
      return '_FORM_STATE_IS_MISSING_';
    }
    if (!($triggering_element = &$form_state->getTriggeringElement())) {
      // When there is no triggering element available, also let it evaluate to
      // be FALSE.
      return '_TRIGGERING_ELEMENT_IS_MISSING_';
    }
    if ($triggering_element['#name'] === 'op' && !empty($triggering_element['#array_parents'])) {
      return end($triggering_element['#array_parents']);
    }
    return $triggering_element['#name'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->tokenService->replaceClear($this->configuration['trigger_name']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'trigger_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['trigger_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trigger name'),
      '#description' => $this->t('The trigger name is the machine name of the form submit element. Example: <em>submit</em>. A custom trigger name can be defined for example with the action <em>"Form: add submit button"</em>.'),
      '#default_value' => $this->configuration['trigger_name'],
      '#weight' => -70,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['trigger_name'] = $form_state->getValue('trigger_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
