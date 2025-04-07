<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

use Drupal\project_browser\ProjectBrowser\Project;

/**
 * An interface for activators that can expose follow-up tasks for a project.
 *
 * @api
 *   This interface is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
interface TasksInterface extends ActivatorInterface {

  /**
   * Returns a set of follow-up tasks for a project.
   *
   * Tasks are exposed as simple links, but could link anywhere. Examples:
   * - The configuration form for a module.
   * - A setup wizard or dashboard created by a recipe.
   * - An uninstall confirmation form for a module.
   * - ...etc.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project object.
   * @param string|null $source_id
   *   (optional) The ID of the source plugin that exposed the project, if
   *   known. Defaults to NULL.
   *
   * @return \Drupal\Core\Link[]
   *   An array of follow-up task links for the project.
   */
  public function getTasks(Project $project, ?string $source_id = NULL): array;

}
