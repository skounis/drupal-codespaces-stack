<?php

declare(strict_types=1);

namespace Drupal\project_browser;

use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\ActivatorInterface;
use Drupal\project_browser\ProjectBrowser\Project;

/**
 * A generalized activator that can handle any type of project.
 *
 * This is a service collector that tries to delegate to the first registered
 * activator that says it supports a given project.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ActivationManager {

  /**
   * The registered activators.
   *
   * @var \Drupal\project_browser\Activator\ActivatorInterface[]
   */
  private array $activators = [];

  /**
   * Registers an activator.
   *
   * @param \Drupal\project_browser\Activator\ActivatorInterface $activator
   *   The activator to register.
   */
  public function addActivator(ActivatorInterface $activator): void {
    if (in_array($activator, $this->activators, TRUE)) {
      return;
    }
    $this->activators[] = $activator;
  }

  /**
   * Determines if a particular project is activated on the current site.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project to check.
   *
   * @return \Drupal\project_browser\Activator\ActivationStatus
   *   The state of the project on the current site.
   */
  public function getStatus(Project $project): ActivationStatus {
    return $this->getActivatorForProject($project)->getStatus($project);
  }

  /**
   * Returns the registered activator to handle a given project.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project object.
   *
   * @return \Drupal\project_browser\Activator\ActivatorInterface
   *   The activator which can handle the given project.
   *
   * @throws \InvalidArgumentException
   *   Thrown if none of the registered activators can handle the given project.
   */
  public function getActivatorForProject(Project $project): ActivatorInterface {
    foreach ($this->activators as $activator) {
      if ($activator->supports($project)) {
        return $activator;
      }
    }
    throw new \InvalidArgumentException("The project '$project->machineName' is not supported by any registered activators.");
  }

  /**
   * Activates a project on the current site.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   The project to activate.
   *
   * @return \Drupal\Core\Ajax\CommandInterface[]|null
   *   The AJAX commands, or lack thereof, returned by the first registered
   *   activator that supports the given project.
   */
  public function activate(Project $project): ?array {
    return $this->getActivatorForProject($project)->activate($project);
  }

}
