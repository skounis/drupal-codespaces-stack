<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\State\StateInterface;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\ActivatorInterface;
use Drupal\project_browser\ProjectBrowser\Project;

/**
 * A test activator that simply logs a state message.
 */
final class TestActivator implements ActivatorInterface {

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * Sets the projects which will be handled this by activator.
   *
   * @param string ...$projects
   *   The Composer package names of the projects to handle.
   */
  public static function handle(string ...$projects): void {
    \Drupal::state()->set('test activator will handle', $projects);
  }

  /**
   * Sets whether to throw an error when activating a specific project.
   *
   * @param string $package_name
   *   The Composer package name of the project.
   * @param bool $error
   *   Whether or not to throw an exception when activating the given project.
   */
  public static function setErrorOnActivate(string $package_name, bool $error): void {
    $errors = \Drupal::state()->get('test activator errors', []);
    $errors[$package_name] = $error;
    \Drupal::state()->set('test activator errors', $errors);
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Project $project): bool {
    $will_handle = $this->state->get('test activator will handle', []);
    return in_array($project->packageName, $will_handle, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(Project $project): ActivationStatus {
    $activated_projects = $this->state->get("test activator", []);

    if (in_array($project->id, $activated_projects, TRUE)) {
      return ActivationStatus::Active;
    }
    elseif ($project->machineName === 'pinky_brain') {
      return ActivationStatus::Present;
    }
    return ActivationStatus::Absent;
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Project $project): ?array {
    $error = $this->state->get('test activator errors', []);
    if (!empty($error[$project->packageName])) {
      throw new \RuntimeException("Error while activating $project->packageName");
    }

    $activated_projects = $this->state->get("test activator", []);
    $activated_projects[] = $project->id;
    $this->state->set("test activator", $activated_projects);
    return NULL;
  }

}
