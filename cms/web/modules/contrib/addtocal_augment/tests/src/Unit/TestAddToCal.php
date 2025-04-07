<?php

namespace Drupal\Tests\addtocal_augment\Unit;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\addtocal_augment\Plugin\DateAugmenter\AddToCal;

/**
 * Override methods for testing.
 */
class TestAddToCal extends AddToCal {

  /**
   * Override parent::defaultConfiguration() which uses String Translation.
   */
  public function defaultConfiguration() {
    return [
      'event_title' => '',
      'location' => '',
      'description' => '',
      'retain_spacing' => FALSE,
      'max_desc' => 60,
      'ellipsis' => TRUE,
      'past_events' => FALSE,
      'label' => 'Add to calendar',
      'target' => '',
    ];
  }

  /**
   * Override parent::getCurrentDate().
   */
  protected function getCurrentDate() {
    // Do NOT call parent::getCurrentDate() inside this function
    // or you will receive the original error.
    $cdt = new \DateTimeZone('America/Chicago');
    $settings = ['langcode' => 'en'];
    return DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-27 00:00:00', $cdt, $settings);
  }

}
