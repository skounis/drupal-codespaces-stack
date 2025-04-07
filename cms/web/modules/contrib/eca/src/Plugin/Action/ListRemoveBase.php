<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Base class for actions removing an item from a list.
 */
abstract class ListRemoveBase extends ListOperationBase {

  use PluginFormTrait;

  /**
   * Removes an item from a list as configured.
   *
   * @return mixed
   *   The removed item. May be NULL if no item was removed.
   */
  protected function removeItem(): mixed {
    if (!($list = $this->getItemList())) {
      return NULL;
    }

    $item = NULL;

    $method = $this->configuration['method'];
    if ($method === '_eca_token') {
      $method = $this->getTokenValue('method', 'first');
    }
    switch ($method) {

      case 'first':
        if ($list instanceof DataTransferObject) {
          $item = $list->shift();
        }
        elseif ($values = $list->getValue()) {
          $item = array_shift($values);
          $list->setValue(array_values($values));
        }
        break;

      case 'last':
        if ($list instanceof DataTransferObject) {
          $item = $list->pop();
        }
        elseif ($values = $list->getValue()) {
          $item = array_pop($values);
          $list->setValue($values);
        }
        break;

      case 'index':
        $index = trim((string) $this->tokenService->replaceClear($this->configuration['index']));
        if (!$index || !($list instanceof ComplexDataInterface) || ctype_digit($index)) {
          $index = (int) $index;
        }
        if ($list instanceof DataTransferObject) {
          $item = $list->removeByName($index);
        }
        elseif ($values = $list->getValue()) {
          $item = $values[$index] ?? NULL;
          unset($values[$index]);
          $list->setValue(array_values($values));
        }
        break;

      case 'value':
        $value = $this->getValueToRemove();
        if ($list instanceof DataTransferObject) {
          $item = $list->remove($value);
        }
        elseif ($values = $list->getValue()) {
          if ($value instanceof TypedDataInterface) {
            $value = $value->getValue();
          }
          if (is_scalar($value) && ($list instanceof FieldItemListInterface)) {
            // Flatten the list of values for finding the specified value.
            $item_definition = $list->getFieldDefinition()->getItemDefinition();
            $property_name = NULL;
            if ($item_definition instanceof ComplexDataDefinitionInterface) {
              $property_name = $item_definition->getMainPropertyName();
            }
            foreach ($values as $i => $item) {
              if (is_array($item)) {
                $values[$i] = isset($property_name) ? ($item[$property_name] ?? NULL) : reset($item);
              }
            }
          }
          $index = array_search($value, $values, TRUE);
          if ($index !== FALSE) {
            $item = $values[$index];
            unset($values[$index]);
          }
          $list->setValue(array_values($values));
        }
        break;

    }

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'method' => 'first',
      'index' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#default_value' => $this->configuration['method'],
      '#weight' => 0,
      '#options' => [
        'first' => $this->t('Drop first'),
        'last' => $this->t('Drop last'),
        'index' => $this->t('Drop by specified index key'),
        'value' => $this->t('Drop by specified value'),
      ],
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index key'),
      '#description' => $this->t('When using the method <em>Drop by specified index key</em>, then an index key must be specified here.'),
      '#default_value' => $this->configuration['index'],
      '#weight' => 10,
      '#required' => FALSE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    if ($form_state->getValue('method') === 'index' && (trim((string) $form_state->getValue('index', '')) === '')) {
      $form_state->setError($form['index'], $this->t('You must specify an index when using the "index" method.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['index'] = $form_state->getValue('index');
  }

  /**
   * Get the value to remove, when using the "value" removal method.
   *
   * @return mixed
   *   The value to remove.
   */
  abstract protected function getValueToRemove(): mixed;

}
