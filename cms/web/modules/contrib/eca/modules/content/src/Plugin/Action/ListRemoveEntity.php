<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ListRemoveBase;

/**
 * Action to remove an entity from a list.
 *
 * @Action(
 *   id = "eca_list_remove_entity",
 *   label = @Translation("List: remove entity"),
 *   description = @Translation("Remove an entity from a list and optionally store the removed entity as a token."),
 *   eca_version_introduced = "1.1.0",
 *   type = "entity"
 * )
 */
class ListRemoveEntity extends ListRemoveBase {

  /**
   * The current entity in scope, if given.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $this->entity = $entity;
    $token_name = trim((string) $this->configuration['token_name']);
    $item = $this->removeItem();
    if ($item instanceof EntityAdapter) {
      $item = $item->getEntity();
    }
    if (!($item instanceof EntityInterface)) {
      $item = NULL;
    }
    if ($token_name !== '') {
      $this->tokenService->addTokenData($token_name, $item);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getValueToRemove(): ?EntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'method' => 'value',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of a token that holds the removed entity.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => 30,
      '#eca_token_reference' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['method']['#options']['value'] = $this->t('Drop specified entity');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['token_name'] = $form_state->getValue('token_name');
  }

}
