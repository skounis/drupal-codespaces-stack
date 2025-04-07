<?php

namespace Drupal\scheduler\Plugin\Scheduler;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\scheduler\SchedulerPluginBase;

/**
 * Plugin for Node entity type.
 *
 * @package Drupal\Scheduler\Plugin\Scheduler
 *
 * @SchedulerPlugin(
 *  id = "node_scheduler",
 *  label = @Translation("Node Scheduler Plugin"),
 *  description = @Translation("Provides support for scheduling node entities"),
 *  entityType = "node",
 *  dependency = "node",
 *  develGenerateForm = "devel_generate_form_content",
 *  collectionRoute = "system.admin_content",
 *  userViewRoute = "view.scheduler_scheduled_content.user_page",
 * )
 */
class NodeScheduler extends SchedulerPluginBase implements ContainerFactoryPluginInterface {}
