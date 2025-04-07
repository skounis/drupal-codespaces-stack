<?php

namespace Drupal\eca_development\Drush\Generators;

use DrupalCodeGenerator\InputOutput\Interviewer;

/**
 * Provides general methods for all ECA generators.
 */
trait EcaGeneratorTrait {

  /**
   * Prepares the generator for all plugins.
   *
   * @param array $vars
   *   The variables.
   *
   * @return \DrupalCodeGenerator\InputOutput\Interviewer
   *   The interviewer.
   */
  protected function prepareGenerator(array &$vars): Interviewer {
    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();
    $vars['php_prefix'] = '<?php';
    $vars['purpose'] = $ir->ask('Purpose of the plugin (typically 1 to 3 words)', 'Do something');
    $vars['description'] = $ir->ask('Description', '');
    $vars['module_version'] = $ir->ask('Module version when this plugin gets published', '1.0.0');
    return $ir;
  }

}
