<?php

namespace Drupal\eca_form\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\FormFieldPluginTrait;

/**
 * Base class for conditions using a form field.
 */
abstract class FormFieldConditionBase extends ConditionBase {

  use FormFieldPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return $this->defaultFormFieldConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = $this->buildFormFieldConfigurationForm($form, $form_state);
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateFormFieldConfigurationForm($form, $form_state);
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->submitFormFieldConfigurationForm($form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

}
