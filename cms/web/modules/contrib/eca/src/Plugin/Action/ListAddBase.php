<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Base class for actions adding an item to a list.
 */
abstract class ListAddBase extends ListOperationBase {

  use PluginFormTrait;

  /**
   * Adds an item to a list as configured.
   *
   * @param mixed $item
   *   The item to add.
   */
  public function addItem(mixed $item): void {
    if (!($list = $this->getItemList())) {
      return;
    }

    $method = $this->configuration['method'];
    if ($method === '_eca_token') {
      $method = $this->getTokenValue('method', 'append');
    }
    switch ($method) {

      case 'append':
        if ($list instanceof DataTransferObject) {
          $list->push($item);
        }
        elseif ($list instanceof ListInterface) {
          $list->appendItem($item);
        }
        else {
          $items = $list->getValue();
          $items[] = $item;
          $list->setValue($items);
        }
        break;

      case 'prepend':
        if ($list instanceof DataTransferObject) {
          $list->unshift($item);
        }
        else {
          $items = $list->getValue();
          array_unshift($items, $item);
          $list->setValue($items);
        }
        break;

      case 'set':
        $index = trim((string) $this->tokenService->replaceClear($this->configuration['index']));
        if (!$index || !($list instanceof ComplexDataInterface) || ctype_digit($index)) {
          $index = (int) $index;
        }
        if ($list instanceof ComplexDataInterface) {
          $list->set($index, $item);
        }
        elseif ($list instanceof ListInterface) {
          $list->set($index, $item);
        }
        break;

    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'method' => 'append',
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
      '#description' => $this->t('Options of the specific method to add a value to a list.'),
      '#default_value' => $this->configuration['method'],
      '#weight' => 0,
      '#options' => [
        'append' => $this->t('Append'),
        'prepend' => $this->t('Prepend'),
        'set' => $this->t('Set by specified index key'),
      ],
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index key'),
      '#description' => $this->t('When using the method <em>Set by specified index</em>, then an index key must be specified here.'),
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
    if ($form_state->getValue('method') === 'set' && (trim((string) $form_state->getValue('index', '')) === '')) {
      $form_state->setError($form['index'], $this->t('You must specify an index when using the "set" method.'));
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

}
