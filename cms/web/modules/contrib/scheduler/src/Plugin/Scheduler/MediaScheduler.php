<?php

namespace Drupal\scheduler\Plugin\Scheduler;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\scheduler\SchedulerPluginBase;

/**
 * Plugin for Media entity type.
 *
 * @package Drupal\Scheduler\Plugin\Scheduler
 *
 * @SchedulerPlugin(
 *  id = "media_scheduler",
 *  label = @Translation("Media Scheduler Plugin"),
 *  description = @Translation("Provides support for scheduling media entities"),
 *  entityType = "media",
 *  dependency = "media",
 *  develGenerateForm = "devel_generate_form_media",
 *  userViewRoute = "view.scheduler_scheduled_media.user_page",
 * )
 */
class MediaScheduler extends SchedulerPluginBase implements ContainerFactoryPluginInterface {}
