<?php

namespace Drupal\scheduler\Plugin\Scheduler;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\scheduler\SchedulerPluginBase;

/**
 * Plugin for Taxonomy Term entity type.
 *
 * @package Drupal\Scheduler\Plugin\Scheduler
 *
 * @SchedulerPlugin(
 *  id = "taxonomy_term_scheduler",
 *  label = @Translation("Taxonomy Term Scheduler Plugin"),
 *  description = @Translation("Provides support for scheduling Taxonomy Term entities"),
 *  entityType = "taxonomy_term",
 *  dependency = "taxonomy",
 *  develGenerateForm = "devel_generate_form_term",
 *  collectionRoute = "entity.taxonomy_vocabulary.collection",
 *  schedulerEventClass = "\Drupal\scheduler\Event\SchedulerTaxonomyTermEvents",
 * )
 */
class TaxonomyTermScheduler extends SchedulerPluginBase implements ContainerFactoryPluginInterface {}
