<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Evaluates against the machine name of the entity form operation.
 *
 * @EcaCondition(
 *   id = "eca_form_operation",
 *   label = @Translation("Entity form: compare operation"),
 *   description = @Translation("Evaluates against the machine name of the entity form operation."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class FormOperation extends StringComparisonBase {

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
    $form_object = $form_state->getFormObject();

    if (!($form_object instanceof EntityFormInterface)) {
      // When the current form is not an entity form, we cannot have an
      // info about the operation.
      return '_FORM_IS_NOT_ENTITY_FORM_';
    }

    return $form_object->getOperation();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->tokenService->replaceClear($this->configuration['operation']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'operation' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['operation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operation'),
      '#description' => $this->t('The machine name of the operation. Example: <em>default, save, delete</em>'),
      '#default_value' => $this->configuration['operation'],
      '#weight' => -70,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['operation'] = $form_state->getValue('operation');
    parent::submitConfigurationForm($form, $form_state);
  }

}
