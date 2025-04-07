<?php

namespace Drupal\eca_language\Plugin\ECA\Event;

use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_language\Event\LanguageNegotiateEvent;
use Drupal\eca_language\LanguageEvents;

/**
 * Plugin implementation of ECA language events.
 *
 * @EcaEvent(
 *   id = "eca_language",
 *   deriver = "Drupal\eca_language\Plugin\ECA\Event\LanguageEventDeriver",
 *   eca_version_introduced = "2.0.0"
 * )
 */
class LanguageEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $definitions = [];
    $definitions['negotiate'] = [
      'label' => 'ECA language negotiation',
      'event_name' => LanguageEvents::NEGOTIATE,
      'event_class' => LanguageNegotiateEvent::class,
      'tags' => Tag::RUNTIME,
    ];
    return $definitions;
  }

}
