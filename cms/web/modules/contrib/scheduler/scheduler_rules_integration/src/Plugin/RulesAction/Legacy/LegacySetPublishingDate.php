<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction\Legacy;

use Drupal\scheduler_rules_integration\Plugin\RulesAction\SetPublishingDate;

/**
 * Provides the 'Set date for scheduled unpublishing' action just for nodes.
 *
 * @RulesAction(
 *   id = "scheduler_set_publishing_date_action",
 *   entity_type_id = "node",
 *   label = @Translation("Set date for publishing a content item"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node for scheduling"),
 *       description = @Translation("The node which is to have a scheduled publishing date set"),
 *       assignment_restriction = "selector",
 *     ),
 *     "date" = @ContextDefinition("timestamp",
 *       label = @Translation("The date for publishing"),
 *       description = @Translation("The date when Scheduler will publish the node"),
 *     )
 *   }
 * )
 */
class LegacySetPublishingDate extends SetPublishingDate {}
