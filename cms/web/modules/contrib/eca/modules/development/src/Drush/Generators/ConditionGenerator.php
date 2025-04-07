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
  name: 'plugin:eca:condition',
  description: 'Generates an ECA condition plugin.',
  aliases: ['eca-condition'],
  templatePath: __DIR__ . '/../../../templates/condition',
  type: GeneratorType::MODULE_COMPONENT,
)]
class ConditionGenerator extends BaseGenerator {

  use EcaGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->prepareGenerator($vars);
    $vars['context'] = explode(',', $ir->ask('Supported context (comma separated list, e.g. "node,user")', ''));
    $vars['id'] = mb_strtolower(str_replace([':', ' ', '-', '.', ',', '__'], '_', $vars['purpose']));
    $vars['class'] = '{id|camelize}Condition';
    $assets->addFile('src/Plugin/ECA/Condition/{class}.php', 'plugin.twig');
  }

}
