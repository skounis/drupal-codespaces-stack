<?php

namespace Drupal\eca_misc\Plugin\ECA\Event;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA Events for routing.
 *
 * @EcaEvent(
 *   id = "routing",
 *   deriver = "Drupal\eca_misc\Plugin\ECA\Event\RoutingEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class RoutingEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'alter' => [
        'label' => 'Alter route',
        'event_name' => RoutingEvents::ALTER,
        'event_class' => RouteBuildEvent::class,
        'description' => new TranslatableMarkup('Fires during route collection to alter routes.'),
      ],
      'dynamic' => [
        'label' => 'Allow new routes',
        'event_name' => RoutingEvents::DYNAMIC,
        'event_class' => RouteBuildEvent::class,
        'description' => new TranslatableMarkup('Fires during route collection to allow new routes.'),
      ],
      'finished' => [
        'label' => 'Route building finished',
        'event_name' => RoutingEvents::FINISHED,
        'event_class' => Event::class,
        'description' => new TranslatableMarkup('Fires, when route building has ended.'),
      ],
    ];
  }

}
