<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction\Legacy;

use Drupal\scheduler_rules_integration\Plugin\RulesAction\SetUnpublishingDate;

/**
 * Provides a 'Set date for scheduled unpublishing' action just for nodes.
 *
 * @RulesAction(
 *   id = "scheduler_set_unpublishing_date_action",
 *   entity_type_id = "node",
 *   label = @Translation("Set date for unpublishing a content item"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node for scheduling"),
 *       description = @Translation("The node which is to have a scheduled unpublishing date set"),
 *       assignment_restriction = "selector",
 *     ),
 *     "date" = @ContextDefinition("timestamp",
 *       label = @Translation("The date for unpublishing"),
 *       description = @Translation("The date when Scheduler will unpublish the node"),
 *     )
 *   }
 * )
 */
class LegacySetUnpublishingDate extends SetUnpublishingDate {}
