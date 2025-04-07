<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for flagging form field actions.
 */
abstract class FormFlagFieldActionBase extends FormFieldActionBase {

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  protected bool $automaticJumpToFieldElement = FALSE;

  /**
   * Get the name of the flagging method.
   *
   * @param bool $human_readable
   *   Whether a human-readable name should be returned. When set to TRUE,
   *   then a translatable markup object is being returned. Default is FALSE.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The flag as machine name or human-readable name.
   */
  abstract protected function getFlagName(bool $human_readable = FALSE): string|TranslatableMarkup;

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    if ($element = &$this->getTargetElement()) {
      $flag = $this->configuration['flag'];
      $element['#' . $this->getFlagName()] = $flag;
      $this->flagAllChildren($element, $flag);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'flag' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['flag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set as @flag', ['@flag' => $this->getFlagName(TRUE)]),
      '#default_value' => $this->configuration['flag'],
      '#weight' => -49,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['flag'] = !empty($form_state->getValue('flag'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Flags all children of the element.
   *
   * @param mixed &$element
   *   The element.
   * @param bool $flag
   *   Whether to enable the flag or not.
   */
  protected function flagAllChildren(mixed &$element, bool $flag): void {
    foreach (Element::children($element) as $key) {
      $element[$key]['#' . $this->getFlagName()] = $flag;
      $this->flagAllChildren($element[$key], $flag);
    }
  }

}
