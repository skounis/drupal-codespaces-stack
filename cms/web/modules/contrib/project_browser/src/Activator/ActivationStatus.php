<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

/**
 * Defines the possible states of a project in the current site.
 *
 * @api
 *   This enum is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
enum ActivationStatus {

  // Not physically present, but can be required and activated.
  case Absent;
  // Physically present, but not yet activated.
  case Present;
  // Physically present and activated.
  case Active;

}
