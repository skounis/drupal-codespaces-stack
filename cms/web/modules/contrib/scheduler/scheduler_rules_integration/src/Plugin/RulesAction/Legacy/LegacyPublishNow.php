<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction\Legacy;

use Drupal\scheduler_rules_integration\Plugin\RulesAction\PublishNow;

/**
 * Provides a 'Publish the node immediately' action.
 *
 * @RulesAction(
 *   id = "scheduler_publish_now_action",
 *   entity_type_id = "node",
 *   label = @Translation("Publish a content item immediately"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node to be published now"),
 *       assignment_restriction = "selector",
 *     ),
 *   }
 * )
 */
class LegacyPublishNow extends PublishNow {}
