<?php

namespace Drupal\project_browser\ComposerInstaller;

use Drupal\package_manager\StageBase;

/**
 * Defines a service to perform installs.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class Installer extends StageBase {

  /**
   * {@inheritdoc}
   */
  protected string $type = 'project_browser.installer';

  /**
   * Checks if the stage tempstore lock was created by Project Browser.
   *
   * This is one of several checks performed to determine if it is acceptable
   * to destroy the current stage. Project Browser's unlock functionality uses
   * the "force" option so a stage can be destroyed even if it was created by
   * a different user or during a different session. However, a stage could have
   * been created by another module, such as Automatic Updates. In those cases
   * Project Browser should not have the ability to destroy the stage.
   *
   * This method confirms the staging lock was created by
   * Drupal\project_browser\ComposerInstaller\Installer, and will only permit
   * destroying the stage if true.
   *
   * @return bool
   *   True if the stage tempstore lock was created by Project Browser.
   */
  public function lockCameFromProjectBrowserInstaller(): bool {
    $lock_data = $this->tempStore->get(static::TEMPSTORE_LOCK_KEY);
    return !empty($lock_data[1]) && $lock_data[1] === self::class;
  }

}
