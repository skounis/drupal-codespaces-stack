<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\trash\Handler\DefaultTrashHandler;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a trash handler for the 'menu_link_content' entity type.
 */
class MenuLinkContentTrashHandler extends DefaultTrashHandler implements EventSubscriberInterface {

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected ?WorkspaceManagerInterface $workspaceManager = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function postTrashDelete(EntityInterface $entity): void {
    parent::postTrashDelete($entity);

    // This needs to happen *after* the menu link is trashed, so its menu_tree
    // entry can be deleted.
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
    $entity::preDelete($this->entityTypeManager->getStorage('menu_link_content'), [$entity]);
  }

  /**
   * Removes menu link definitions for trashed menu items.
   */
  public function onPostPublish(WorkspacePostPublishEvent $event): void {
    $menu_link_ids = $event->getPublishedRevisionIds()['menu_link_content'] ?? [];
    if (empty($menu_link_ids)) {
      return;
    }

    // Handle menu links that were trashed in the workspace.
    $this->trashManager->executeInTrashContext('ignore', function () use ($menu_link_ids) {
      $this->workspaceManager?->executeOutsideWorkspace(function () use ($menu_link_ids) {
        /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $menu_links */
        $menu_links = $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadMultipleRevisions(array_keys($menu_link_ids));

        foreach ($menu_links as $menu_link) {
          if (trash_entity_is_deleted($menu_link)) {
            $this->menuLinkManager->removeDefinition($menu_link->getPluginId(), FALSE);
          }
        }
      });
    });
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    if (class_exists(WorkspacePostPublishEvent::class)) {
      // During workspace publishing, menu link definitions for trashed items
      // could be created or updated, so this subscriber needs to run last in
      // order to remove them.
      $events[WorkspacePostPublishEvent::class][] = ['onPostPublish', -100];
    }

    return $events;
  }

}
