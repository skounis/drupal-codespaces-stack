<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

use Drupal\project_browser\ProjectBrowser\Project;

/**
 * An interface for activators that can generate activation instructions.
 *
 * @api
 *   This interface is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
interface InstructionsInterface extends ActivatorInterface {

  /**
   * Returns instructions for how to activate a project.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   The project to activate.
   * @param string|null $source_id
   *   (optional) The ID of the source plugin that exposed the project, if
   *   known. Defaults to NULL.
   *
   * @return string
   *   One of:
   *   - A translated string containing human-readable instructions for how to
   *     activate the given project. The UI will display these instructions in
   *     a modal dialog.
   *   - An absolute URL which this project's "Install" button should link to in
   *     the UI.
   */
  public function getInstructions(Project $project, ?string $source_id = NULL): string;

}
