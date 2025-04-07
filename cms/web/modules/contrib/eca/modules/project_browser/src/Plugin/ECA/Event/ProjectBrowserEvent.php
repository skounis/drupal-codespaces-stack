<?php

namespace Drupal\eca_project_browser\Plugin\ECA\Event;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_project_browser\Event\ProjectBrowserEvents;
use Drupal\eca_project_browser\Event\ProjectBrowserSourceInfoAlterEvent;

/**
 * Plugin implementation of the ECA Events for project browser.
 *
 * @EcaEvent(
 *   id = "project_browser",
 *   deriver = "Drupal\eca_project_browser\Plugin\ECA\Event\ProjectBrowserEventDeriver",
 *   eca_version_introduced = "2.1.2"
 * )
 */
class ProjectBrowserEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'source_info_alter' => [
        'label' => 'Alter source plugin info',
        'event_name' => ProjectBrowserEvents::SOURCE_INFO_ALTER,
        'event_class' => ProjectBrowserSourceInfoAlterEvent::class,
        'description' => new TranslatableMarkup('Fires during project browser source plugin alter.'),
      ],
    ];
  }

}
