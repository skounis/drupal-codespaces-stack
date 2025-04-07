<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition\Legacy;

use Drupal\scheduler_rules_integration\Plugin\Condition\UnpublishingIsEnabled;

/**
 * Provides 'Unpublishing is enabled' condition for nodes only.
 *
 * @Condition(
 *   id = "scheduler_condition_unpublishing_is_enabled",
 *   label = @Translation("Node type is enabled for scheduled unpublishing"),
 *   category = @Translation("Content (Scheduler)"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node",
 *       label = @Translation("Node"),
 *       description = @Translation("The node to check for the type being enabled for scheduled unpublishing."),
 *       assignment_restriction = "selector",
 *     )
 *   }
 * )
 */
class LegacyUnpublishingIsEnabled extends UnpublishingIsEnabled {}
