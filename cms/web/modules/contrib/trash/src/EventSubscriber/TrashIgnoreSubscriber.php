<?php

declare(strict_types=1);

namespace Drupal\trash\EventSubscriber;

use Drupal\Core\DefaultContent\PreImportEvent;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Update\UpdateKernel;
use Drupal\trash\TrashManagerInterface;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\Event\WorkspacePublishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens to events where trash context has to be ignored.
 */
class TrashIgnoreSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected TrashManagerInterface $trashManager,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Sets the trash context to ignore if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The KernelEvent to process.
   */
  public function onRequestPreRouting(KernelEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    // This is needed so upgrades affecting entities will affect all entities,
    // no matter if they have been trashed.
    $is_update_kernel = $event->getKernel() instanceof UpdateKernel;

    $has_trash_query = $event->getRequest()->query->has('in_trash');

    if ($is_update_kernel || $has_trash_query) {
      $this->trashManager->setTrashContext('ignore');
    }
  }

  /**
   * Sets the trash context to ignore if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The KernelEvent to process.
   */
  public function onRequest(KernelEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    // Allow trashed entities to be displayed on the workspace manage page.
    if ($this->routeMatch->getRouteName() === 'entity.workspace.canonical') {
      $this->trashManager->setTrashContext('ignore');
    }
  }

  /**
   * Ignores the trash context when default_content imports content.
   *
   * @param \Drupal\Core\DefaultContent\PreImportEvent $event
   *   The default_content pre-import event.
   */
  public function onDefaultContentPreImport(PreImportEvent $event): void {
    $this->trashManager->setTrashContext('ignore');
  }

  /**
   * Ignores the trash context when publishing a workspace.
   *
   * @param \Drupal\workspaces\Event\WorkspacePublishEvent $event
   *   The workspace publish event.
   */
  public function onWorkspacePrePublish(WorkspacePublishEvent $event): void {
    $this->trashManager->setTrashContext('ignore');
  }

  /**
   * Reverts the trash context after publishing a workspace.
   *
   * @param \Drupal\workspaces\Event\WorkspacePublishEvent $event
   *   The workspace publish event.
   */
  public function onWorkspacePostPublish(WorkspacePublishEvent $event): void {
    $this->trashManager->setTrashContext('active');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Our ignore subscriber needs to run before language negotiation (which has
    // a priority of 255) in order to allow route enhancers (e.g. entity param
    // converter) to load the deleted entity.
    $events[KernelEvents::REQUEST][] = ['onRequestPreRouting', 256];

    // Add another subscriber for setting the ignore trash context when the
    // current route is known.
    $events[KernelEvents::REQUEST][] = ['onRequest'];

    if (class_exists(WorkspacePublishEvent::class)) {
      $events[WorkspacePrePublishEvent::class][] = ['onWorkspacePrePublish'];
      $events[WorkspacePostPublishEvent::class][] = ['onWorkspacePostPublish'];
    }
    if (class_exists(PreImportEvent::class)) {
      $events[PreImportEvent::class][] = ['onDefaultContentPreImport'];
    }

    return $events;
  }

}
