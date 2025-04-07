<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Base class for list operation actions.
 */
abstract class ListOperationBase extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf(!is_null($this->getItemList()));
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'list_token' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['list_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token containing the list'),
      '#description' => $this->t('Provide the name of the token that contains a list of items. If the list does not exist yet, a new one will be created.'),
      '#default_value' => $this->configuration['list_token'],
      '#weight' => -20,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['list_token'] = $form_state->getValue('list_token');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Get the item list.
   *
   * When the item list does not exist yet, a new list will be created.
   *
   * @return \Drupal\Core\TypedData\TraversableTypedDataInterface
   *   The item list, or NULL if the targeted token cannot perform the requested
   *   operation. For example, a single field item that is not a list cannot
   *   handle list operations like appending or prepending items.
   */
  protected function getItemList(): ?TraversableTypedDataInterface {
    $list_token = trim((string) $this->configuration['list_token']);
    $token = $this->tokenService;
    if (!$token->hasTokenData($list_token)) {
      $token->addTokenData($list_token, DataTransferObject::create([]));
    }
    $list = $token->getTokenData($list_token);
    return ($list instanceof ListInterface || $list instanceof DataTransferObject) ? $list : NULL;
  }

}
