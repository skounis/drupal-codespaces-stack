<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;

/**
 * Prevents uninstall of project_browser_test, for testing purposes.
 */
final class TestUninstallValidator implements ModuleUninstallValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function validate($module): array {
    if ($module === 'project_browser_test') {
      return ["Can't touch this!"];
    }
    return [];
  }

}
