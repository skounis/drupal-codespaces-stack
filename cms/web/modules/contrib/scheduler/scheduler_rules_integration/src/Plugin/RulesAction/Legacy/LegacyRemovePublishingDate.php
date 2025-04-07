<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction\Legacy;

use Drupal\scheduler_rules_integration\Plugin\RulesAction\RemovePublishingDate;

/**
 * Provides a 'Remove date for scheduled publishing' action, for nodes only.
 *
 * @RulesAction(
 *   id = "scheduler_remove_publishing_date_action",
 *   entity_type_id = "node",
 *   label = @Translation("Remove date for publishing a content item"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node from which to remove the scheduled publishing date"),
 *       assignment_restriction = "selector",
 *     ),
 *   }
 * )
 */
class LegacyRemovePublishingDate extends RemovePublishingDate {}
