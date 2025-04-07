<?php

declare(strict_types=1);

namespace Drupal\RecipeKit\Installer;

use Drupal\Core\Form\FormInterface as CoreFormInterface;

/**
 * Defines an interface for forms shown to the user in the early installer.
 */
interface FormInterface extends CoreFormInterface {

  /**
   * Returns an install task definition for this form.
   *
   * @param array $install_state
   *   The current install state.
   *
   * @return array
   *   An install task definition.
   *
   * @see hook_install_tasks()
   */
  public static function toInstallTask(array $install_state): array;

}
