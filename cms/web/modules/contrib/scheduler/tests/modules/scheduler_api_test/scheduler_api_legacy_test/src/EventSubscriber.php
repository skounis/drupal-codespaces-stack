<?php

namespace Drupal\scheduler_api_legacy_test;

use Drupal\scheduler\SchedulerEvent;
use Drupal\scheduler\SchedulerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests events fired on node objects.
 *
 * These tests use the legacy Scheduler event namespaces, to demonstrate that
 * the aliases created for the original node events work correctly.
 */
class EventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {

    // Initialize the array to avoid 'variable is undefined' phpcs error.
    $events = [];

    // The values in the arrays give the function names below.
    // These six events are the originals, dispatched for Nodes.
    $events[SchedulerEvents::PRE_PUBLISH][] = ['apiTestNodePrePublish'];
    $events[SchedulerEvents::PUBLISH][] = ['apiTestNodePublish'];
    $events[SchedulerEvents::PRE_UNPUBLISH][] = ['apiTestNodePreUnpublish'];
    $events[SchedulerEvents::UNPUBLISH][] = ['apiTestNodeUnpublish'];
    $events[SchedulerEvents::PRE_PUBLISH_IMMEDIATELY][] = ['apiTestNodePrePublishImmediately'];
    $events[SchedulerEvents::PUBLISH_IMMEDIATELY][] = ['apiTestNodePublishImmediately'];

    return $events;
  }

  /**
   * Operations to perform before Scheduler publishes a node.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePrePublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // Before publishing a node make it sticky.
    if (!$node->isPublished() && strpos($node->title->value, 'API LEGACY TEST') === 0) {
      $node->setSticky(TRUE);
      $event->setNode($node);
    }
  }

  /**
   * Operations to perform after Scheduler publishes a node.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // After publishing a node promote it to the front page.
    if ($node->isPublished() && strpos($node->title->value, 'API LEGACY TEST') === 0) {
      $node->setPromoted(TRUE)->save();
      $event->setNode($node);
    }
  }

  /**
   * Operations to perform before Scheduler unpublishes a node.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePreUnpublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // Before unpublishing a node make it unsticky.
    if ($node->isPublished() && strpos($node->title->value, 'API LEGACY TEST') === 0) {
      $node->setSticky(FALSE);
      $event->setNode($node);
    }
  }

  /**
   * Operations to perform after Scheduler unpublishes a node.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodeUnpublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // After unpublishing a node remove it from the front page.
    if (!$node->isPublished() && strpos($node->title->value, 'API LEGACY TEST') === 0) {
      $node->setPromoted(FALSE)->save();
      $event->setNode($node);
    }
  }

  /**
   * Operations before Scheduler publishes a node immediately not via cron.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePrePublishImmediately(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // Before publishing immediately set the node to sticky.
    if (!$node->isPromoted() && strpos($node->title->value, 'API LEGACY TEST') === 0) {
      $node->setSticky(TRUE);
      $event->setNode($node);
    }
  }

  /**
   * Operations after Scheduler publishes a node immediately not via cron.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePublishImmediately(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // After publishing immediately set the node to promoted and change the
    // title.
    if (!$node->isPromoted() && strpos($node->title->value, 'API LEGACY TEST') === 0) {
      $node->setTitle('Published immediately')
        ->setPromoted(TRUE);
      $event->setNode($node);
    }
  }

}
