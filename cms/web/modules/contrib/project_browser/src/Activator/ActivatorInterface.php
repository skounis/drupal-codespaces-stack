<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

use Drupal\project_browser\ProjectBrowser\Project;

/**
 * Defines an interface for services which can activate projects.
 *
 * An activator is the "source of truth" about the state of a particular project
 * in the current site -- for example, an activator that handles modules knows
 * if the module is already installed.
 *
 * @api
 *   This interface is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
interface ActivatorInterface {

  /**
   * Determines if a particular project is activated on the current site.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project to check.
   *
   * @return \Drupal\project_browser\Activator\ActivationStatus
   *   The state of the project on the current site.
   */
  public function getStatus(Project $project): ActivationStatus;

  /**
   * Determines if this activator can handle a particular project.
   *
   * For example, an activator that handles themes might return TRUE from this
   * method if the project's Composer package type is `drupal-theme`.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project to check.
   *
   * @return bool
   *   TRUE if this activator is responsible for the given project, FALSE
   *   otherwise.
   */
  public function supports(Project $project): bool;

  /**
   * Activates a project on the current site.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   The project to activate.
   *
   * @return \Drupal\Core\Ajax\CommandInterface[]|null
   *   Optionally, an array of AJAX commands to run on the front end.
   */
  public function activate(Project $project): ?array;

}
