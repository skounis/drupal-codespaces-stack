<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Evaluates against the machine name of the entity form display mode.
 *
 * @EcaCondition(
 *   id = "eca_content_form_display_mode",
 *   label = @Translation("Entity form: compare display mode"),
 *   description = @Translation("Evaluates against the machine name of the entity form display mode."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class EntityFormDisplayMode extends StringComparisonBase {

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

    if (!($form_object instanceof ContentEntityFormInterface)) {
      // When the current form is not a content entity form, we cannot have
      // a form display.
      return '_FORM_IS_NOT_CONTENT_ENTITY_FORM_';
    }

    return $form_object->getFormDisplay($form_state)->getMode();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->tokenService->replaceClear($this->configuration['display_mode']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'display_mode' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['display_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form display mode'),
      '#description' => $this->t('The machine name of the display mode. Example: <em>default</em>'),
      '#default_value' => $this->configuration['display_mode'],
      '#weight' => -70,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['display_mode'] = $form_state->getValue('display_mode');
    parent::submitConfigurationForm($form, $form_state);
  }

}
