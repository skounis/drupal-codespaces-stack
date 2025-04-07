<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition\Legacy;

use Drupal\scheduler_rules_integration\Plugin\Condition\ScheduledForUnpublishing;

/**
 * Provides 'Node is scheduled for unpublishing' condition.
 *
 * @Condition(
 *   id = "scheduler_condition_node_scheduled_for_unpublishing",
 *   label = @Translation("Node is scheduled for unpublishing"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node to check for having a scheduled unpublishing date."),
 *       assignment_restriction = "selector",
 *     )
 *   }
 * )
 */
class LegacyScheduledForUnpublishing extends ScheduledForUnpublishing {}
