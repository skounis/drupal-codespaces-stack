<?php

namespace Drupal\Tests\eca_modeller_bpmn\Kernel\Model;

use Drupal\Tests\eca\Kernel\Model\Base;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_model_plugin_config_validation\Plugin\ECA\Modeller\DummyModeller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Testing the BPMN base modeller.
 *
 * @group eca
 * @group eca_modeller_bpmn
 */
class BpmnBaseModellerTest extends Base {

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
   * The dummy BPMN modeller.
   *
   * @var \Drupal\eca_test_model_plugin_config_validation\Plugin\ECA\Modeller\DummyModeller|null
   */
  protected ?DummyModeller $modeller;

  /**
   * The dummy ECA config entity.
   *
   * @var \Drupal\eca\Entity\Eca|null
   */
  protected ?Eca $eca;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    /** @var \Drupal\eca\PluginManager\Modeller $modelManager */
    $modelManager = \Drupal::service('plugin.manager.eca.modeller');
    /* @noinspection PhpFieldAssignmentTypeMismatchInspection */
    $this->modeller = $modelManager->createInstance('dummy');
    $this->eca = \Drupal::entityTypeManager()->getStorage('eca')->load('eca_test_0011');
  }

  /**
   * Test ModellerBpmnBase::createNewModel.
   */
  public function testCreateNewModel(): void {
    // Test with an new, empty model.
    $id = '';
    $modelData = $this->modeller->prepareEmptyModelData($id);
    $eca = $this->modeller->createNewModel($id, $modelData);
    $this->assertNotSame($id, $eca->id(), "ID of empty ECA should not be $id");
    $this->assertSame(mb_strtolower($id), $eca->id(), 'ID of empty ECA should be' . mb_strtolower($id));
    $this->assertTrue($eca->isNew(), 'Empty ECA model should be new.');
    $this->modeller->save($modelData);
    $eca = $this->modeller->getEca();
    $this->assertEmpty($eca->getUsedEvents(), 'Empty ECA should not contain any events.');

    // Test with the data of the existing dummy model.
    $id = $this->modeller->generateId();
    $modelData = str_replace('eca_test_0011', $id, $this->eca->getModel()->getModeldata());
    $eca = $this->modeller->createNewModel($id, $modelData);
    $this->assertNotSame($id, $eca->id(), "ID of ECA should not be $id");
    $this->assertSame(mb_strtolower($id), $eca->id(), 'ID of ECA should be' . mb_strtolower($id));
    $this->assertTrue($eca->isNew(), 'ECA model should be new.');
    $this->modeller->save($modelData);
    $eca = $this->modeller->getEca();
    $this->assertNotEmpty($eca->getUsedEvents(), 'ECA should contain events.');
  }

  /**
   * Test ModellerBpmnBase::updateModel.
   */
  public function testUpdateModel(): void {
    $modeller = $this->eca->getModeller();
    $changed = $modeller->updateModel($this->eca->getModel());
    $this->assertFalse($changed, 'Model should not have changed during update.');
  }

  /**
   * Test ModellerBpmnBase::enable and ModellerBpmnBase::disable.
   */
  public function testEnableDisableModel(): void {
    $modeller = $this->eca->getModeller();
    $this->assertTrue($modeller->getEca()->status(), 'ECA should initially be enabled.');
    $modeller->disable();
    $this->assertFalse($modeller->getEca()->status(), 'ECA should then be disabled.');
    $modeller->enable();
    $this->assertTrue($modeller->getEca()->status(), 'ECA should finally be enabled again.');
  }

  /**
   * Test ModellerBpmnBase::clone.
   */
  public function testCloneModel(): void {
    $modeller = $this->eca->getModeller();
    $this->assertSame($this->eca->id(), $modeller->getEca()->id(), 'ECA should be the same.');
    $eca = $modeller->clone();
    $this->assertNotNull($eca, 'ECA should not be NULL.');
    $this->assertNotSame($this->eca->id(), $eca->id(), 'Cloned ECA should not be the same as original ECA.');

    $orgEvents = $this->eca->getUsedEvents();
    $newEvents = $eca->getUsedEvents();
    $this->assertCount(count($orgEvents), $newEvents, 'Both ECA entities should have the same number of events.');
    foreach ($orgEvents as $id => $event) {
      $this->assertArrayHasKey($id, $newEvents, "Cloned ECA should have the $id event.");
      $this->assertEquals($event->getConfiguration()['event_id'], $newEvents[$id]->getConfiguration()['event_id'], "Configured event if of the $id events should be the same.");
    }
  }

  /**
   * Test ModellerBpmnBase::export.
   */
  public function testExportModel(): void {
    $modeller = $this->eca->getModeller();
    $export = $modeller->export();
    $this->assertInstanceOf(BinaryFileResponse::class, $export, 'Export should be a binary response.');
    $this->assertEquals('gz', $export->getFile()->getExtension(), "Export file extension should be gz.");
  }

  /**
   * Test ModellerBpmnBase::setModeldata.
   */
  public function testSetModelData(): void {
    $modeller = $this->eca->getModeller();
    $id = $this->modeller->generateId();
    $modelData = str_replace('eca_test_0011', $id, $this->eca->getModel()->getModeldata());
    $modeller->setModeldata($modelData);
    $this->assertEquals($id, $modeller->getId(), "New ID should be $id.");
  }

  /**
   * Test the templates.
   */
  public function testTemplates(): void {
    $templates = $this->modeller->getTemplatesForTesting();
    $this->assertIsArray($templates, 'Templates should be an array');
    foreach ($templates as $template) {
      foreach ($template['properties'] as $property) {
        if ($property['type'] === 'Dropdown') {
          if (count($property['choices']) === 2 &&
            in_array($property['choices'][0]['name'], ['yes', 'no'], TRUE)) {
            $name = $property['binding']['name'];
            $label = $property['label'];
            $this->assertEquals(self::getExpectedCheckbox($name, $label, $property['value'], $property['description'] ?? ''),
              $property, "Checkbox $name for plugin $label should be properly prepared.");
          }
          else {
            $name = $property['binding']['name'];
            $label = $property['label'];
            $this->assertEquals(self::getExpectedOptionFields($name, $label, $property['value'], $property['choices'], $property['description'] ?? '', $property['constraints'] ?? NULL),
              $property, "Option list $name for plugin $label should be properly prepared.");
          }
        }
      }
    }
  }

  /**
   * Gets the expected option fields.
   *
   * @param string $name
   *   The name of the field.
   * @param string $label
   *   The label of the field.
   * @param string $value
   *   The value of the field.
   * @param array $choices
   *   The available options for the field.
   * @param string $description
   *   The optional description.
   * @param array|null $constraints
   *   The optional constraints.
   *
   * @return array
   *   The expected option field definition.
   */
  private static function getExpectedOptionFields(string $name, string $label, string $value, array $choices, string $description, ?array $constraints = NULL): array {
    $options = [
      'label' => $label,
      'type' => 'Dropdown',
      'value' => $value,
      'editable' => TRUE,
      'binding' => [
        'type' => 'camunda:field',
        'name' => $name,
      ],
      'choices' => $choices,
    ];
    if (!empty($description)) {
      $options['description'] = $description;
    }
    if ($constraints !== NULL) {
      $options['constraints'] = $constraints;
    }
    return $options;
  }

  /**
   * Gets the expected checkbox.
   *
   * @param string $name
   *   The name of the checkbox.
   * @param string $label
   *   The label of the checkbox.
   * @param string $value
   *   The condition of the checkbox.
   * @param string $description
   *   The optional description.
   *
   * @return array
   *   The expected checkbox field definition.
   */
  private static function getExpectedCheckbox(string $name, string $label, string $value, string $description): array {
    $checkbox = [
      'label' => $label,
      'type' => 'Dropdown',
      'value' => $value,
      'editable' => TRUE,
      'binding' => [
        'type' => 'camunda:field',
        'name' => $name,
      ],
      'choices' => [
        [
          'name' => 'no',
          'value' => 'no',
        ],
        [
          'name' => 'yes',
          'value' => 'yes',
        ],
      ],
    ];
    if (!empty($description)) {
      $checkbox['description'] = $description;
    }
    return $checkbox;
  }

}
