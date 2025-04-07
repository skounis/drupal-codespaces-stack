<?php

declare(strict_types=1);

namespace Drupal\trash\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeListenerInterface;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines a class for listening to entity schema changes.
 */
class TrashEntitySchemaSubscriber implements EntityTypeListenerInterface, EventSubscriberInterface {

  use EntityTypeEventSubscriberTrait;

  public function __construct(
    protected TrashManagerInterface $trashManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return static::getEntityTypeEvents();
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type): void {
    // Remove the deleted entity type from the Trash settings.
    if ($this->trashManager->isEntityTypeEnabled($entity_type)) {
      $trash_settings = $this->configFactory->getEditable('trash.settings');
      $enabled_entity_types = $trash_settings->get('enabled_entity_types');
      unset($enabled_entity_types[$entity_type->id()]);
      $trash_settings->set('enabled_entity_types', $enabled_entity_types)->save();
    }
  }

}
