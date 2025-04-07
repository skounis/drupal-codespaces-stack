<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition\Legacy;

use Drupal\scheduler_rules_integration\Plugin\Condition\ScheduledForPublishing;

/**
 * Provides 'Node is scheduled for publishing' condition.
 *
 * @Condition(
 *   id = "scheduler_condition_node_scheduled_for_publishing",
 *   label = @Translation("Node is scheduled for publishing"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node to check for having a scheduled publishing date."),
 *       assignment_restriction = "selector",
 *     )
 *   }
 * )
 */
class LegacyScheduledForPublishing extends ScheduledForPublishing {}
