<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_content\Plugin\EntitySaveTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Saves a content entity.
 *
 * @Action(
 *   id = "eca_save_entity",
 *   label = @Translation("Entity: save"),
 *   description = @Translation("Saves a new or updates an existing content entity."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class SaveEntity extends ConfigurableActionBase {

  use EntitySaveTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    if (!($object instanceof ContentEntityInterface)) {
      $access_result = AccessResult::forbidden();
    }
    elseif ($object->isNew()) {
      /**
       * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
       */
      $access_handler = $this->entityTypeManager->getHandler($object->getEntityTypeId(), 'access');
      $access_result = $access_handler->createAccess($object->bundle(), $account, [], TRUE);
    }
    else {
      $access_result = $object->access('update', $account, TRUE);
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }

    $this->saveEntity($entity);
  }

}
