<?php

namespace Drupal\scheduler_api_test;

use Drupal\scheduler\Event\SchedulerCommerceProductEvents;
use Drupal\scheduler\Event\SchedulerEvent;
use Drupal\scheduler\Event\SchedulerMediaEvents;
use Drupal\scheduler\Event\SchedulerNodeEvents;
use Drupal\scheduler\Event\SchedulerTaxonomyTermEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests events fired on entity objects.
 *
 * These events allow modules to react to the Scheduler process being performed.
 * They are all triggered during Scheduler cron processing with the exception of
 * 'pre_publish_immediately' and 'publish_immediately' which are triggered from
 * scheduler_entity_presave().
 *
 * The node event tests use the 'sticky' and 'promote' fields as a simple way to
 * check the processing. There are extra conditional checks on isPublished() to
 * make the tests stronger so they fail if the calls are in the wrong place.
 *
 * The media tests cannot use 'sticky' and 'promote' as these fields do not
 * exist, so the media name is altered instead. This is also the case with
 * products and taxonomy terms.
 *
 * To allow this API test module to be enabled interactively (for development
 * and testing) we must avoid unwanted side-effects on other non-test nodes.
 * This is done simply by checking that the titles start with 'API TEST'.
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
    $events[SchedulerNodeEvents::PRE_PUBLISH][] = ['apiTestNodePrePublish'];
    $events[SchedulerNodeEvents::PUBLISH][] = ['apiTestNodePublish'];
    $events[SchedulerNodeEvents::PRE_UNPUBLISH][] = ['apiTestNodePreUnpublish'];
    $events[SchedulerNodeEvents::UNPUBLISH][] = ['apiTestNodeUnpublish'];
    $events[SchedulerNodeEvents::PRE_PUBLISH_IMMEDIATELY][] = ['apiTestNodePrePublishImmediately'];
    $events[SchedulerNodeEvents::PUBLISH_IMMEDIATELY][] = ['apiTestNodePublishImmediately'];

    // These six events are dispatched for Media entity types only.
    $events[SchedulerMediaEvents::PRE_PUBLISH][] = ['apiTestMediaPrePublish'];
    $events[SchedulerMediaEvents::PUBLISH][] = ['apiTestMediaPublish'];
    $events[SchedulerMediaEvents::PRE_UNPUBLISH][] = ['apiTestMediaPreUnpublish'];
    $events[SchedulerMediaEvents::UNPUBLISH][] = ['apiTestMediaUnpublish'];
    $events[SchedulerMediaEvents::PRE_PUBLISH_IMMEDIATELY][] = ['apiTestMediaPrePublishImmediately'];
    $events[SchedulerMediaEvents::PUBLISH_IMMEDIATELY][] = ['apiTestMediaPublishImmediately'];

    // These six events are dispatched for Product entity types only.
    $events[SchedulerCommerceProductEvents::PRE_PUBLISH][] = ['apiTestProductPrePublish'];
    $events[SchedulerCommerceProductEvents::PUBLISH][] = ['apiTestProductPublish'];
    $events[SchedulerCommerceProductEvents::PRE_UNPUBLISH][] = ['apiTestProductPreUnpublish'];
    $events[SchedulerCommerceProductEvents::UNPUBLISH][] = ['apiTestProductUnpublish'];
    $events[SchedulerCommerceProductEvents::PRE_PUBLISH_IMMEDIATELY][] = ['apiTestProductPrePublishImmediately'];
    $events[SchedulerCommerceProductEvents::PUBLISH_IMMEDIATELY][] = ['apiTestProductPublishImmediately'];

    // These six events are dispatched for Taxonomy Term entity types only.
    $events[SchedulerTaxonomyTermEvents::PRE_PUBLISH][] = ['apiTestTaxonomyTermPrePublish'];
    $events[SchedulerTaxonomyTermEvents::PUBLISH][] = ['apiTestTaxonomyTermPublish'];
    $events[SchedulerTaxonomyTermEvents::PRE_UNPUBLISH][] = ['apiTestTaxonomyTermPreUnpublish'];
    $events[SchedulerTaxonomyTermEvents::UNPUBLISH][] = ['apiTestTaxonomyTermUnpublish'];
    $events[SchedulerTaxonomyTermEvents::PRE_PUBLISH_IMMEDIATELY][] = ['apiTestTaxonomyTermPrePublishImmediately'];
    $events[SchedulerTaxonomyTermEvents::PUBLISH_IMMEDIATELY][] = ['apiTestTaxonomyTermPublishImmediately'];

    return $events;
  }

  /**
   * Operations to perform before Scheduler publishes a node.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePrePublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // Before publishing a node make it sticky.
    if (!$node->isPublished() && strpos($node->title->value, 'API TEST') === 0) {
      $node->setSticky(TRUE);
      $event->setNode($node);
    }
  }

  /**
   * Operations to perform after Scheduler publishes a node.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // After publishing a node promote it to the front page.
    if ($node->isPublished() && strpos($node->title->value, 'API TEST') === 0) {
      $node->setPromoted(TRUE)->save();
      $event->setNode($node);
    }
  }

  /**
   * Operations to perform before Scheduler unpublishes a node.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePreUnpublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // Before unpublishing a node make it unsticky.
    if ($node->isPublished() && strpos($node->title->value, 'API TEST') === 0) {
      $node->setSticky(FALSE);
      $event->setNode($node);
    }
  }

  /**
   * Operations to perform after Scheduler unpublishes a node.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodeUnpublish(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // After unpublishing a node remove it from the front page.
    if (!$node->isPublished() && strpos($node->title->value, 'API TEST') === 0) {
      $node->setPromoted(FALSE)->save();
      $event->setNode($node);
    }
  }

  /**
   * Operations before Scheduler publishes a node immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePrePublishImmediately(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // Before publishing immediately set the node to sticky.
    if (!$node->isPromoted() && strpos($node->title->value, 'API TEST') === 0) {
      $node->setSticky(TRUE);
      $event->setNode($node);
    }
  }

  /**
   * Operations after Scheduler publishes a node immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestNodePublishImmediately(SchedulerEvent $event) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $event->getNode();
    // After publishing immediately set the node to promoted and change the
    // title.
    if (!$node->isPromoted() && strpos($node->title->value, 'API TEST') === 0) {
      $node->setTitle('Published immediately')
        ->setPromoted(TRUE);
      $event->setNode($node);
    }
  }

  /**
   * Generic helper function to do the PrePublish work.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  private function apiTestPrePublish(SchedulerEvent $event) {
    $entity = $event->getEntity();
    if (!$entity->isPublished() && strpos($entity->label(), "API TEST {$entity->getEntityTypeId()}") === 0) {
      $label_field = $entity->getEntityType()->getKey('label');
      $entity->set($label_field, "API TEST {$entity->getEntityTypeId()} - changed by PRE_PUBLISH event");
      $event->setEntity($entity);
    }
  }

  /**
   * Generic helper function to do the Publish work.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  private function apiTestPublish(SchedulerEvent $event) {
    $entity = $event->getEntity();
    // The label will be changed here only if it has already been changed by the
    // PRE_PUBLISH event. This will demonstrate that both events worked.
    if ($entity->isPublished() && $entity->label() == "API TEST {$entity->getEntityTypeId()} - changed by PRE_PUBLISH event") {
      $label_field = $entity->getEntityType()->getKey('label');
      $entity->set($label_field, "API TEST {$entity->getEntityTypeId()} - altered a second time by PUBLISH event")->save();
      $event->setEntity($entity);
    }
  }

  /**
   * Generic helper function to do the PreUnpublish work.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  private function apiTestPreUnpublish(SchedulerEvent $event) {
    $entity = $event->getEntity();
    if ($entity->isPublished() && strpos($entity->label(), "API TEST {$entity->getEntityTypeId()}") === 0) {
      $label_field = $entity->getEntityType()->getKey('label');
      $entity->set($label_field, "API TEST {$entity->getEntityTypeId()} - changed by PRE_UNPUBLISH event");
      $event->setEntity($entity);
    }
  }

  /**
   * Generic helper function to do the Unpublish work.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  private function apiTestUnpublish(SchedulerEvent $event) {
    $entity = $event->getEntity();
    // The name will be changed here only if it has already been changed by the
    // PRE_UNPUBLISH event. This will demonstrate that both events worked.
    if (!$entity->isPublished() && $entity->label() == "API TEST {$entity->getEntityTypeId()} - changed by PRE_UNPUBLISH event") {
      $label_field = $entity->getEntityType()->getKey('label');
      $entity->set($label_field, "API TEST {$entity->getEntityTypeId()} - altered a second time by UNPUBLISH event")->save();
      $event->setEntity($entity);
    }
  }

  /**
   * Generic helper function to do the PrePublishImmediately work.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestPrePublishImmediately(SchedulerEvent $event) {
    $entity = $event->getEntity();
    if (!$entity->isPublished() && strpos($entity->label(), "API TEST {$entity->getEntityTypeId()}") === 0) {
      $label_field = $entity->getEntityType()->getKey('label');
      $entity->set($label_field, "API TEST {$entity->getEntityTypeId()} - changed by PRE_PUBLISH_IMMEDIATELY event");
      $event->setEntity($entity);
    }
  }

  /**
   * Generic helper function to do the PublishImmediately work.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestPublishImmediately(SchedulerEvent $event) {
    $entity = $event->getEntity();
    // The name will be changed here only if it has already been changed in the
    // PRE_PUBLISH_IMMEDIATELY event function, to show that both events worked.
    if ($entity->label() == "API TEST {$entity->getEntityTypeId()} - changed by PRE_PUBLISH_IMMEDIATELY event") {
      $label_field = $entity->getEntityType()->getKey('label');
      $entity->set($label_field, "API TEST {$entity->getEntityTypeId()} - altered a second time by PUBLISH_IMMEDIATELY event");
      $event->setEntity($entity);
    }
  }

  /**
   * Operations to perform before Scheduler publishes a media item.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestMediaPrePublish(SchedulerEvent $event) {
    $this->apiTestPrePublish($event);
  }

  /**
   * Operations to perform after Scheduler publishes a media item.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestMediaPublish(SchedulerEvent $event) {
    $this->apiTestPublish($event);
  }

  /**
   * Operations to perform before Scheduler unpublishes a media item.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestMediaPreUnpublish(SchedulerEvent $event) {
    $this->apiTestPreUnpublish($event);
  }

  /**
   * Operations to perform after Scheduler unpublishes a media item.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestMediaUnpublish(SchedulerEvent $event) {
    $this->apiTestUnpublish($event);
  }

  /**
   * Operations before Scheduler publishes a media immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestMediaPrePublishImmediately(SchedulerEvent $event) {
    $this->apiTestPrePublishImmediately($event);
  }

  /**
   * Operations after Scheduler publishes a media immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestMediaPublishImmediately(SchedulerEvent $event) {
    $this->apiTestPublishImmediately($event);
  }

  /**
   * Operations to perform before Scheduler publishes a commerce product.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestProductPrePublish(SchedulerEvent $event) {
    $this->apiTestPrePublish($event);
  }

  /**
   * Operations to perform after Scheduler publishes a commerce product.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestProductPublish(SchedulerEvent $event) {
    $this->apiTestPublish($event);
  }

  /**
   * Operations to perform before Scheduler unpublishes a commerce product.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestProductPreUnpublish(SchedulerEvent $event) {
    $this->apiTestPreUnpublish($event);
  }

  /**
   * Operations to perform after Scheduler unpublishes a commerce product.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestProductUnpublish(SchedulerEvent $event) {
    $this->apiTestUnpublish($event);
  }

  /**
   * Operations before Scheduler publishes a product immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestProductPrePublishImmediately(SchedulerEvent $event) {
    $this->apiTestPrePublishImmediately($event);
  }

  /**
   * Operations after Scheduler publishes a product immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestProductPublishImmediately(SchedulerEvent $event) {
    $this->apiTestPublishImmediately($event);
  }

  /**
   * Operations to perform before Scheduler publishes a taxonomy term.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestTaxonomyTermPrePublish(SchedulerEvent $event) {
    $this->apiTestPrePublish($event);
  }

  /**
   * Operations to perform after Scheduler publishes a taxonomy term.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestTaxonomyTermPublish(SchedulerEvent $event) {
    $this->apiTestPublish($event);
  }

  /**
   * Operations to perform before Scheduler unpublishes a taxonomy term.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestTaxonomyTermPreUnpublish(SchedulerEvent $event) {
    $this->apiTestPreUnpublish($event);
  }

  /**
   * Operations to perform after Scheduler unpublishes a taxonomy term.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestTaxonomyTermUnpublish(SchedulerEvent $event) {
    $this->apiTestUnpublish($event);
  }

  /**
   * Operations before Scheduler publishes a term immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestTaxonomyTermPrePublishImmediately(SchedulerEvent $event) {
    $this->apiTestPrePublishImmediately($event);
  }

  /**
   * Operations after Scheduler publishes a term immediately not via cron.
   *
   * @param \Drupal\scheduler\Event\SchedulerEvent $event
   *   The scheduler event.
   */
  public function apiTestTaxonomyTermPublishImmediately(SchedulerEvent $event) {
    $this->apiTestPublishImmediately($event);
  }

}
