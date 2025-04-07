<?php

namespace Drupal\scheduler_rules_integration\Event;

use Drupal\node\NodeInterface;

/**
 * Class for all Rules node events.
 */
class RulesNodeEvent extends EventBase {

  /**
   * Define constants to convert the event identifier into the full event name.
   *
   * To retain backwards compatibility the event names for node events remain as
   * originally specified in scheduler_rules_integration.rules.events.yml. The
   * format is different from the new events derived for other entity types.
   * However, the identifiers (CRON_PUBLISHED, NEW_FOR_PUBLISHING, etc) are the
   * same for all types and this is how the actual event names are retrieved.
   */
  const CRON_PUBLISHED = 'scheduler_has_published_this_node_event';
  const CRON_UNPUBLISHED = 'scheduler_has_unpublished_this_node_event';
  const NEW_FOR_PUBLISHING = 'scheduler_new_node_is_scheduled_for_publishing_event';
  const NEW_FOR_UNPUBLISHING = 'scheduler_new_node_is_scheduled_for_unpublishing_event';
  const EXISTING_FOR_PUBLISHING = 'scheduler_existing_node_is_scheduled_for_publishing_event';
  const EXISTING_FOR_UNPUBLISHING = 'scheduler_existing_node_is_scheduled_for_unpublishing_event';

  /**
   * The node which is being processed.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $node;

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node which is being processed.
   */
  public function __construct(NodeInterface $node) {
    $this->node = $node;
  }

  /**
   * Returns the entity which is being processed.
   */
  public function getEntity() {
    // The Rules module requires the entity to be stored in a specifically named
    // property which will obviously vary according to the entity type being
    // processed. This generic getEntity() method is not strictly required by
    // Rules but is added for convenience when manipulating the event entity.
    return $this->node;
  }

}
