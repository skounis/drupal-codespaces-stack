<?php

namespace Drupal\eca_test_model_plugin_config_validation\Plugin\ECA\Modeller;

use Drupal\eca\Plugin\ECA\Modeller\ModellerInterface;
use Drupal\eca_modeller_bpmn\ModellerBpmnBase;

/**
 * Plugin implementation of the ECA Modeller.
 *
 * @EcaModeller(
 *   id = "dummy",
 *   nodocs = true
 * )
 */
class DummyModeller extends ModellerBpmnBase {

  /**
   * A list of all templates.
   *
   * @var array
   */
  protected array $templates;

  /**
   * {@inheritdoc}
   */
  protected function xmlNsPrefix(): string {
    return 'bpmn2:';
  }

  /**
   * {@inheritdoc}
   */
  public function exportTemplates(): ModellerInterface {
    $this->templates = $this->getTemplates();
    return parent::exportTemplates();
  }

  /**
   * Prepares and returns the templates for testing.
   *
   * @return array
   *   The list of templates.
   */
  public function getTemplatesForTesting(): array {
    $this->exportTemplates();
    return $this->templates;
  }

}
