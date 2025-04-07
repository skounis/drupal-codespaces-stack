<?php

namespace Drupal\Tests\eca_modeller_bpmn\Kernel\Model;

use Drupal\Tests\eca\Kernel\Model\Base;

/**
 * Model test for saving an ECA entity with config validation.
 *
 * @group eca
 * @group eca_model
 * @group eca_modeller_bpmn
 */
class PluginConfigValidationTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eca_base',
    'eca_modeller_bpmn',
    'eca_test_model_plugin_config_validation',
    'eca_ui',
  ];

  /**
   * Tests the saving of an ECA entity and its validation.
   */
  public function testPluginConfigValidation(): void {
    /** @var \Drupal\eca\PluginManager\Modeller $modelManager */
    $modelManager = \Drupal::service('plugin.manager.eca.modeller');
    /** @var \Drupal\eca_test_model_plugin_config_validation\Plugin\ECA\Modeller\DummyModeller $modeller */
    $modeller = $modelManager->createInstance('dummy');
    /** @var \Drupal\eca\Entity\Model $model */
    $model = \Drupal::entityTypeManager()->getStorage('eca_model')->load('eca_test_0011');
    $data = $model->getModeldata();

    $fieldOrigin = '<camunda:string>correct</camunda:string>';
    $fieldWrong = '<camunda:string>wrong</camunda:string>';
    $fieldCorrect = '<camunda:string>my test value</camunda:string>';

    // Test that the model won't be saved with the config value "wrong".
    $wrongData = str_replace($fieldOrigin, $fieldWrong, $data);
    $modeller->save($wrongData);

    $this->assertErrorMessages([
      'action "Test: Dummy action to validate configuration" (Dummy): This value is not allowed.',
    ]);
    /** @var \Drupal\eca\Entity\Model $model */
    $eca = \Drupal::entityTypeManager()->getStorage('eca')->load('eca_test_0011');
    $actions = $eca->get('actions');
    $this->assertSame('correct', $actions['Dummy']['configuration']['dummy'], 'The config value "correct" should not have changed.');

    // Test that the model will be saved with the custom value "my test value".
    $correctData = str_replace($fieldOrigin, $fieldCorrect, $data);
    $modeller->save($correctData);

    $this->assertErrorMessages([]);
    $eca = \Drupal::entityTypeManager()->getStorage('eca')->load('eca_test_0011');
    $actions = $eca->get('actions');
    $this->assertSame('my test value', $actions['Dummy']['configuration']['dummy'], 'The config value "correct" should have changed to "my test value".');
  }

}
