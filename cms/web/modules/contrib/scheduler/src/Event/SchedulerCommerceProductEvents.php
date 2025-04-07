<?php

namespace Drupal\scheduler\Event;

/**
 * Lists the six events dispatched by Scheduler for Commerce Product entities.
 */
final class SchedulerCommerceProductEvents {

  /**
   * The event triggered after a commerce product is published immediately.
   *
   * This event allows modules to react after an entity is published
   * immediately when being saved after editing. The event listener method
   * receives a \Drupal\Core\Entity\EntityInterface instance.
   *
   * @Event
   *
   * @see \Drupal\scheduler\Event\SchedulerEvent
   *
   * @var string
   */
  const PUBLISH_IMMEDIATELY = 'scheduler.commerce_product_publish_immediately';

  /**
   * The event triggered after a commerce product is published by cron.
   *
   * This event allows modules to react after an entity is published by Cron.
   * The event listener receives a \Drupal\Core\Entity\EntityInterface instance.
   *
   * @Event
   *
   * @see \Drupal\scheduler\Event\SchedulerEvent
   *
   * @var string
   */
  const PUBLISH = 'scheduler.commerce_product_publish';

  /**
   * The event triggered before a commerce product is published immediately.
   *
   * This event allows modules to react before an entity is published
   * immediately when being saved after editing. The event listener method
   * receives a \Drupal\Core\Entity\EntityInterface instance.
   *
   * @Event
   *
   * @see \Drupal\scheduler\Event\SchedulerEvent
   *
   * @var string
   */
  const PRE_PUBLISH_IMMEDIATELY = 'scheduler.commerce_product_pre_publish_immediately';

  /**
   * The event triggered before a commerce product is published by cron.
   *
   * This event allows modules to react before an entity is published by Cron.
   * The event listener receives a \Drupal\Core\Entity\EntityInterface instance.
   *
   * @Event
   *
   * @see \Drupal\scheduler\Event\SchedulerEvent
   *
   * @var string
   */
  const PRE_PUBLISH = 'scheduler.commerce_product_pre_publish';

  /**
   * The event triggered before a commerce product is unpublished by cron.
   *
   * This event allows modules to react before an entity is unpublished by Cron.
   * The event listener receives a \Drupal\Core\Entity\EntityInterface instance.
   *
   * @Event
   *
   * @see \Drupal\scheduler\Event\SchedulerEvent
   *
   * @var string
   */
  const PRE_UNPUBLISH = 'scheduler.commerce_product_pre_unpublish';

  /**
   * The event triggered after a commerce product is unpublished by cron.
   *
   * This event allows modules to react after an entity is unpublished by Cron.
   * The event listener receives a \Drupal\Core\Entity\EntityInterface instance.
   *
   * @Event
   *
   * @see \Drupal\scheduler\Event\SchedulerEvent
   *
   * @var string
   */
  const UNPUBLISH = 'scheduler.commerce_product_unpublish';

}
