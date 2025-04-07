<?php

namespace Drupal\scheduler\Plugin\Scheduler;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\scheduler\SchedulerPluginBase;

/**
 * Plugin for Commerce Product entity type.
 *
 * @package Drupal\Scheduler\Plugin\Scheduler
 *
 * @SchedulerPlugin(
 *  id = "commerce_product_scheduler",
 *  label = @Translation("Commerce Product Scheduler Plugin"),
 *  description = @Translation("Provides support for scheduling Commerce Product entities"),
 *  entityType = "commerce_product",
 *  dependency = "commerce_product",
 *  schedulerEventClass = "\Drupal\scheduler\Event\SchedulerCommerceProductEvents",
 *  publishAction = "commerce_publish_product",
 *  unpublishAction = "commerce_unpublish_product"
 * )
 */
class CommerceProductScheduler extends SchedulerPluginBase implements ContainerFactoryPluginInterface {}
