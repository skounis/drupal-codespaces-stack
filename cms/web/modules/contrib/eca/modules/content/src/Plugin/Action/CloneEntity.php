<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca_content\Event\ContentEntityPrepareForm;

/**
 * Clone an existing content entity without saving it.
 *
 * @Action(
 *   id = "eca_clone_entity",
 *   label = @Translation("Entity: clone existing"),
 *   description = @Translation("Clone an existing content entity without saving it."),
 *   eca_version_introduced = "2.1.0",
 *   type = "entity"
 * )
 */
class CloneEntity extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The instantiated entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'label' => '',
      'published' => FALSE,
      'owner' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('Provide the name of a token that holds the new entity.'),
      '#weight' => -60,
      '#eca_token_reference' => TRUE,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity label'),
      '#default_value' => $this->configuration['label'],
      '#description' => $this->t('The label of the new entity.'),
      '#weight' => -30,
    ];
    $form['published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Published'),
      '#default_value' => $this->configuration['published'],
      '#description' => $this->t('Whether the entity should be published or not.'),
      '#weight' => -20,
    ];
    $form['owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner UID'),
      '#default_value' => $this->configuration['owner'],
      '#description' => $this->t('The owner UID of the new entity.'),
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['published'] = !empty($form_state->getValue('published'));
    $this->configuration['owner'] = $form_state->getValue('owner');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!($object instanceof ContentEntityInterface)) {
      $access_result = AccessResult::forbidden('No content entity provided');
    }
    else {
      /** @var \Drupal\Core\Access\AccessResultInterface $access_result */
      $access_result = parent::access($object, $account, TRUE);
      if ($access_result->isAllowed()) {
        $account = $account ?? $this->currentUser;
        $entity_type_id = $object->getEntityTypeId();
        $bundle = $object->bundle();
        if (!$this->entityTypeManager->hasHandler($entity_type_id, 'access')) {
          $access_result = AccessResult::forbidden('Cannot determine access without an access handler.');
        }
        else {
          /**
           * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
           */
          $access_handler = $this->entityTypeManager->getHandler($entity_type_id, 'access');
          $access_result = $access_handler->createAccess($bundle, $account, [], TRUE);
        }
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }
    $newEntity = $entity->createDuplicate();
    $config = &$this->configuration;
    $definition = $this->entityTypeManager->getDefinition($newEntity->getEntityTypeId());
    $entity_keys = $definition->get('entity_keys');
    if (isset($entity_keys['label'])) {
      $label = trim((string) $this->tokenService->replace($config['label'], [], ['clear' => TRUE]));
      if ($label !== '') {
        $newEntity->set($entity_keys['label'], $label);
      }
    }
    if (isset($entity_keys['published'])) {
      $newEntity->set($entity_keys['published'], (int) $this->configuration['published']);
    }
    if (isset($entity_keys['owner'])) {
      $owner_id = trim((string) $this->tokenService->replace($config['owner'], [], ['clear' => TRUE]));
      if ($owner_id === '') {
        $owner_id = $this->currentUser->id();
      }
      $newEntity->set($entity_keys['owner'], $owner_id);
    }
    if ($newEntity->hasField('created')) {
      $newEntity->set('created', $this->time->getRequestTime());
    }
    $this->entity = $newEntity;
    $this->tokenService->addTokenData($config['token_name'], $newEntity);
    if ($this->event instanceof ContentEntityPrepareForm) {
      $this->event->setEntity($newEntity);
    }
  }

}
