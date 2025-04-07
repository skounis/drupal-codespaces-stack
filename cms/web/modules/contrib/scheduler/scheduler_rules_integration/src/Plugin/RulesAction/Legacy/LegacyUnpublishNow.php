<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction\Legacy;

use Drupal\scheduler_rules_integration\Plugin\RulesAction\UnpublishNow;

/**
 * Provides an 'Unpublish the node immediately' action.
 *
 * @RulesAction(
 *   id = "scheduler_unpublish_now_action",
 *   entity_type_id = "node",
 *   label = @Translation("Unpublish a content item immediately"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node to be unpublished now"),
 *       assignment_restriction = "selector",
 *     ),
 *   }
 * )
 */
class LegacyUnpublishNow extends UnpublishNow {}
