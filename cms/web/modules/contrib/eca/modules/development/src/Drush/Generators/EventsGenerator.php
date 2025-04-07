<?php

namespace Drupal\eca_development\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

/**
 * Code generator for ECA events support.
 */
#[Generator(
  name: 'plugin:eca:events',
  description: 'Generates files for ECA event support.',
  aliases: ['eca-events'],
  templatePath: __DIR__ . '/../../../templates/events',
  type: GeneratorType::MODULE_COMPONENT,
)]
class EventsGenerator extends BaseGenerator {

  use EcaGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $this->prepareGenerator($vars);
    $assets->addFile('src/Event/MyEvent.php', 'event.twig');
    $assets->addFile('src/Plugin/ECA/Event/EcaEvent.php', 'plugin.twig');
    $assets->addFile('src/Plugin/ECA/Event/EcaEventDeriver.php', 'deriver.twig');
    $assets->addFile('src/EcaEvents.php', 'events.twig');
  }

}
