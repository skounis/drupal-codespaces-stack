<?php

declare(strict_types=1);

namespace Drupal\RecipeKit\Installer\Drush\Generators;

use Composer\InstalledVersions;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;

#[Generator(
  name: 'recipe-kit:installer',
  description: 'Generates a stub install profile built on the recipe installer kit.',
  templatePath: __DIR__,
)]
final class RecipeInstallerGenerator extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);

    $vars['machine_name'] = $ir->askMachineName();
    $vars['name'] = $ir->askName();
    $vars['finish_url'] = $ir->ask('Enter a Drupal path where users should be redirected after installing.', '/');
    $vars['theme'] = $ir->ask('Enter the machine name of the theme to use during the install process.', 'claro');
    $vars['rink_version'] = InstalledVersions::getPrettyVersion('drupal/recipe_installer_kit');

    $assets->addFile('{machine_name}.info.yml', 'info.yml.twig');
    $assets->addFile('{machine_name}.profile', 'profile.twig');
    $assets->addFile('composer.json', 'composer.json.twig');
    $assets->addFile('config/install/user.role.administrator.yml', 'user.role.administrator.yml');
  }

}
