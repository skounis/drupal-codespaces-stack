<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition\Legacy;

use Drupal\scheduler_rules_integration\Plugin\Condition\PublishingIsEnabled;

/**
 * Provides a 'Publishing is enabled' condition for nodes only.
 *
 * @Condition(
 *   id = "scheduler_condition_publishing_is_enabled",
 *   label = @Translation("Node type is enabled for scheduled publishing"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node to check for the type being enabled for scheduled publishing."),
 *       assignment_restriction = "selector",
 *     )
 *   }
 * )
 */
class LegacyPublishingIsEnabled extends PublishingIsEnabled {}
