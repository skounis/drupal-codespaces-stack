<?php

namespace Drupal\eca_development\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

/**
 * Code generator for ECA condition plugins.
 */
#[Generator(
  name: 'plugin:eca:action',
  description: 'Generates an ECA action plugin.',
  aliases: ['eca-action'],
  templatePath: __DIR__ . '/../../../templates/action',
  type: GeneratorType::MODULE_COMPONENT,
)]
class ActionGenerator extends BaseGenerator {

  use EcaGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->prepareGenerator($vars);
    $vars['type'] = $ir->ask('Type (e.g. "entity" or "node" or "user", normally empty)', '');
    $vars['id'] = mb_strtolower(str_replace([':', ' ', '-', '.', ',', '__'], '_', $vars['purpose']));
    $vars['class'] = '{id|camelize}Action';
    $assets->addFile('src/Plugin/Action/{class}.php', 'plugin.twig');
  }

}
