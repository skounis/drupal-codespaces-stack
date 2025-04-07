<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction\Legacy;

use Drupal\scheduler_rules_integration\Plugin\RulesAction\RemoveUnpublishingDate;

/**
 * Provides a 'Remove date for scheduled unpublishing' action for nodes only.
 *
 * @RulesAction(
 *   id = "scheduler_remove_unpublishing_date_action",
 *   entity_type_id = "node",
 *   label = @Translation("Remove date for unpublishing a content item"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node from which to remove the scheduled unpublishing date"),
 *       assignment_restriction = "selector",
 *     ),
 *   }
 * )
 */
class LegacyRemoveUnpublishingDate extends RemoveUnpublishingDate {}
