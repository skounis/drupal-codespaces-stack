<?php

declare(strict_types=1);

namespace Drupal\trash\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to the config save event for trash.settings.
 */
class TrashConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository,
    protected RouteBuilderInterface $routeBuilder,
    protected DrupalKernelInterface $kernel,
  ) {}

  /**
   * Enables or disables trash integration for entity types.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The ConfigCrudEvent to process.
   */
  public function onSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'trash.settings') {
      $supported_entity_types = array_filter($this->entityTypeManager->getDefinitions(), function ($entity_type) {
        return $this->trashManager->isEntityTypeSupported($entity_type);
      });
      $enabled_entity_types = $event->getConfig()->get('enabled_entity_types');

      // Work around core bug #2605144, which doesn't provide the original
      // config data on import, only on regular save.
      // @see https://www.drupal.org/project/drupal/issues/2605144
      foreach ($supported_entity_types as $entity_type_id => $entity_type) {
        $field_storage_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);

        // Enable trash integration for the requested entity types.
        if (isset($enabled_entity_types[$entity_type_id]) && !isset($field_storage_definitions['deleted'])) {
          $this->trashManager->enableEntityType($entity_type);
        }

        // Disable trash integration for the rest of the entity types.
        if (!isset($enabled_entity_types[$entity_type_id])
            && isset($field_storage_definitions['deleted'])
            && $field_storage_definitions['deleted']->getProvider() === 'trash') {
          $this->trashManager->disableEntityType($entity_type);
        }
      }

      // When an entity type is enabled or disabled, the router needs to be
      // rebuilt to add the corresponding tabs in the trash UI.
      $this->routeBuilder->setRebuildNeeded();

      // The container also needs to be rebuilt in order to update the trash
      // handler services.
      // @see \Drupal\trash\Handler\TrashHandlerPass::process()
      $this->kernel->invalidateContainer();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
