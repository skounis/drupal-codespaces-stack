<?php

namespace Drupal\scheduler_rules_integration\Event;

use Drupal\media\MediaInterface;

/**
 * Class for all Rules media events.
 */
class RulesMediaEvent extends EventBase {

  /**
   * Define constants to convert the event identifier into the full event name.
   *
   * The final event names here are defined in the event deriver and are
   * different in format from the event names for node events, as originally
   * coded long-hand in scheduler_rules_integration.rules.events.yml.
   * However, the identifiers (CRON_PUBLISHED, NEW_FOR_PUBLISHING, etc) are the
   * same for all types and this is how the actual event names are retrieved.
   */
  const CRON_PUBLISHED = 'scheduler:media_has_been_published_via_cron';
  const CRON_UNPUBLISHED = 'scheduler:media_has_been_unpublished_via_cron';
  const NEW_FOR_PUBLISHING = 'scheduler:new_media_is_scheduled_for_publishing';
  const NEW_FOR_UNPUBLISHING = 'scheduler:new_media_is_scheduled_for_unpublishing';
  const EXISTING_FOR_PUBLISHING = 'scheduler:existing_media_is_scheduled_for_publishing';
  const EXISTING_FOR_UNPUBLISHING = 'scheduler:existing_media_is_scheduled_for_unpublishing';

  /**
   * The media item which is being processed.
   *
   * @var \Drupal\media\MediaInterface
   */
  public $media;

  /**
   * Constructs the object.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item which is being processed.
   */
  public function __construct(MediaInterface $media) {
    $this->media = $media;
  }

  /**
   * Returns the entity which is being processed.
   */
  public function getEntity() {
    // The Rules module requires the entity to be stored in a specifically named
    // property which will obviously vary according to the entity type being
    // processed. This generic getEntity() method is not strictly required by
    // Rules but is added for convenience when manipulating the event entity.
    return $this->media;
  }

}
