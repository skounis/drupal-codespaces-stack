<?php

declare(strict_types=1);

namespace Drupal\project_browser;

/**
 * The different project types known to Project Browser.
 *
 * @see \Drupal\project_browser\ProjectBrowser\Project
 *
 * @api
 *   This enum is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
enum ProjectType: string {

  // A contributed or custom Drupal module.
  case Module = 'module';
  // A Drupal recipe.
  case Recipe = 'recipe';

}
