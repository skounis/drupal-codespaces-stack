<?php

namespace Drupal\eca_base\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * ECA condition plugin for evaluating whether a certain item is in a list.
 *
 * @EcaCondition(
 *   id = "eca_list_contains",
 *   label = @Translation("List: contains item"),
 *   description = @Translation("Evaluate whether a certain item is in a list."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class ListContains extends ConditionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'list_token' => '',
      'method' => 'value',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['list_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token containing the list'),
      '#description' => $this->t('Provide the name of the token that contains a list of items.'),
      '#default_value' => $this->configuration['list_token'],
      '#weight' => -200,
      '#eca_token_reference' => TRUE,
    ];
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#default_value' => $this->configuration['method'],
      '#weight' => -190,
      '#options' => [
        'index' => $this->t('Lookup index key'),
        'value' => $this->t('Lookup list value'),
      ],
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value to lookup'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -180,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['list_token'] = $form_state->getValue('list_token');
    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['value'] = $form_state->getValue('value');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $list = $this->getItemList();
    $result = FALSE;
    $method = $this->configuration['method'];
    if ($method === '_eca_token') {
      $method = $this->getTokenValue('method', 'value');
    }

    switch ($method) {

      case 'index':
        $index = trim((string) $this->tokenService->replaceClear($this->configuration['value']));
        if (!$index || !($list instanceof ComplexDataInterface) || ctype_digit($index)) {
          $index = (int) $index;
        }
        if ($list instanceof DataTransferObject) {
          try {
            $properties = $list->getProperties(TRUE);
            $result = isset($properties[$index]);
          }
          catch (MissingDataException) {
            // Can be ignored.
          }
        }
        elseif ($values = ($list->getValue() ?? [])) {
          $result = isset($values[$index]);
        }
        break;

      case 'value':
        $value = $this->tokenService->getOrReplace($this->configuration['value']);
        if ($value instanceof TypedDataInterface) {
          $value = $value->getValue();
        }
        if ($list instanceof DataTransferObject) {
          try {
            $result = (NULL !== (clone $list)->remove($value)) || $this->containsTrimmedValue($value, $list->toArray());
          }
          catch (MissingDataException) {
            // Can be ignored.
          }
        }
        elseif (($value instanceof EntityInterface) && ($list instanceof EntityReferenceFieldItemListInterface)) {
          if ($entities = $list->referencedEntities()) {
            $result = (NULL !== DataTransferObject::create($entities)->remove($value));
          }
        }
        elseif ($values = $list->getValue()) {
          $result = (in_array($value, $values, TRUE)) || $this->containsTrimmedValue($value, $values);
        }
        break;

    }

    return $this->negationCheck($result);
  }

  /**
   * Checks whether the trimmed form of the value is contained in the values.
   *
   * @param mixed $value
   *   The value to lookup.
   * @param mixed $values
   *   The values to check.
   *
   * @return bool
   *   Returns TRUE if it contains the trimmed value, FALSE otherwise.
   */
  protected function containsTrimmedValue(mixed $value, mixed $values): bool {
    if (!is_scalar($value) || !is_array($values) || empty($values)) {
      return FALSE;
    }

    $value = trim((string) $value);
    $result = FALSE;

    array_walk_recursive($values, static function ($list_value) use (&$value, &$result) {
      if (is_scalar($list_value) && (trim((string) $list_value) === $value)) {
        $result = TRUE;
      }
    });

    return $result;
  }

  /**
   * Get the item list.
   *
   * @return \Drupal\Core\TypedData\TraversableTypedDataInterface|null
   *   The item list, or NULL if the targeted token is not a list.
   */
  protected function getItemList(): ?TraversableTypedDataInterface {
    $list_token = trim((string) $this->configuration['list_token']);
    $token = $this->tokenService;
    if (!$token->hasTokenData($list_token)) {
      return NULL;
    }
    $list = $token->getTokenData($list_token);
    return ($list instanceof ListInterface || $list instanceof DataTransferObject) ? $list : NULL;
  }

}
