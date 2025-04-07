<?php

namespace Drupal\scheduler\Event;

/**
 * Lists the six events dispatched by Scheduler for Taxonomy Term entities.
 */
final class SchedulerTaxonomyTermEvents {

  /**
   * The event triggered after a taxonomy term is published immediately.
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
  const PUBLISH_IMMEDIATELY = 'scheduler.taxonomy_term_publish_immediately';

  /**
   * The event triggered after a taxonomy term is published by cron.
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
  const PUBLISH = 'scheduler.taxonomy_term_publish';

  /**
   * The event triggered before a taxonomy term is published immediately.
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
  const PRE_PUBLISH_IMMEDIATELY = 'scheduler.taxonomy_term_pre_publish_immediately';

  /**
   * The event triggered before a taxonomy term is published by cron.
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
  const PRE_PUBLISH = 'scheduler.taxonomy_term_pre_publish';

  /**
   * The event triggered before a taxonomy term is unpublished by cron.
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
  const PRE_UNPUBLISH = 'scheduler.taxonomy_term_pre_unpublish';

  /**
   * The event triggered after a taxonomy term is unpublished by cron.
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
  const UNPUBLISH = 'scheduler.taxonomy_term_unpublish';

}
