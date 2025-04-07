<?php

declare(strict_types=1);

namespace Drupal\project_browser_test\Extension;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;

/**
 * Conditional Module installer for test.
 *
 * @see \Drupal\Core\Extension\ModuleInstaller::install
 */
final class TestModuleInstaller implements ModuleInstallerInterface {

  public function __construct(
    private readonly ModuleInstallerInterface $decorated,
  ) {}

  /**
   * Take over install if module name is cream_cheese or kangaroo.
   *
   * @param array $module_list
   *   An array of module machine names.
   * @param bool $enable_dependencies
   *   True if dependencies should be enabled.
   */
  public function install(array $module_list, $enable_dependencies = TRUE): bool {
    if (!empty(array_intersect(['cream_cheese', 'kangaroo'], $module_list))) {
      return TRUE;
    }
    return $this->decorated->install($module_list, $enable_dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(array $module_list, $uninstall_dependents = TRUE): bool {
    return $this->decorated->uninstall($module_list, $uninstall_dependents);
  }

  /**
   * {@inheritdoc}
   */
  public function addUninstallValidator(ModuleUninstallValidatorInterface $uninstall_validator): void {
    $this->decorated->addUninstallValidator($uninstall_validator);
  }

  /**
   * {@inheritdoc}
   */
  public function validateUninstall(array $module_list): array {
    return $this->decorated->validateUninstall($module_list);
  }

}
