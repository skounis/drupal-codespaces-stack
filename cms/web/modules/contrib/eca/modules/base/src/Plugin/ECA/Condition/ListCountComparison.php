<?php

namespace Drupal\eca_base\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca_base\Plugin\ListCountTrait;

/**
 * ECA condition plugin for numerically comparing number of list items.
 *
 * @EcaCondition(
 *   id = "eca_count",
 *   label = @Translation("Compare number of list items"),
 *   description = @Translation("Condition to compare the number of list items."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class ListCountComparison extends ScalarComparison {

  use ListCountTrait;

  /**
   * {@inheritdoc}
   */
  protected function getLeftValue(): string {
    return (string) $this->countValue($this->configuration['left']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['left']['#type'] = 'textfield';
    $form['left']['#title'] = $this->t('Name of token containing the list');
    $form['left']['#description'] = $this->t('Provide the name of the token that contains a list from which the number of items should be counted.');
    $form['right']['#type'] = 'textfield';
    $form['operator']['#default_value'] = static::COMPARE_EQUALS;
    $form['type']['#default_value'] = static::COMPARE_TYPE_NUMERIC;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(string $id): ?array {
    if ($id === 'operator') {
      return [
        static::COMPARE_EQUALS => $this->t('equals'),
        static::COMPARE_GREATERTHAN => $this->t('greater than'),
        static::COMPARE_LESSTHAN => $this->t('less than'),
        static::COMPARE_ATMOST => $this->t('at most'),
        static::COMPARE_ATLEAST => $this->t('at least'),
      ];
    }
    if ($id === 'type') {
      return [
        static::COMPARE_TYPE_NUMERIC => $this->t('Numeric order'),
      ];
    }
    return parent::getOptions($id);
  }

}
