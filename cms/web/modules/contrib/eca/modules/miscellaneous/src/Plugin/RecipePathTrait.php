<?php

namespace Drupal\eca_misc\Plugin;

use Composer\InstalledVersions;

/**
 * Trait to get path of a recipe based on a given package name.
 *
 * @see \Drupal\eca_misc\Plugin\Action\ApplyRecipe
 * @see \Drupal\eca_misc\Plugin\ECA\Condition\RecipeUsesConfigAction
 */
trait RecipePathTrait {

  /**
   * Returns the path of the recipe pointed to by configuration.
   *
   * @param string $recipe_package_name
   *   The Composer package name of the recipe, which can also be a token.
   *
   * @return string|null
   *   Returns a string if the configured recipe Composer package name (after
   *   token replacement) is an existing directory path. If the path is empty,
   *   does not exist, or cannot be mapped to an installed package, NULL is
   *   returned.
   */
  protected function getRecipePath(string $recipe_package_name): ?string {
    $package_name = $this->tokenService->replace($recipe_package_name);

    // If the package name is the name of an installed Composer-managed package,
    // resolve its installed path. `InstalledVersions` is part of Composer's
    // runtime API and is available as soon as the autoloader is, which is the
    // first thing Drupal loads when it boots up. This does not run the Composer
    // executable or any Composer commands.
    if ($package_name !== '' && InstalledVersions::isInstalled($package_name)) {
      $path = InstalledVersions::getInstallPath($package_name);
      return trim(rtrim($path, '/'));
    }
    // The package_name isn't anything we can work with; give up.
    return NULL;
  }

}
