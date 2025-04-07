<?php

namespace Drupal\Tests\bpmn_io\Kernel\Converter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\bpmn_io\Services\Converter\ConverterInterface;

/**
 * Tests converting different types of ECA-entities.
 *
 * @group bpmn_io
 */
class ConverterTest extends KernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The converter.
   *
   * @var \Drupal\bpmn_io\Services\Converter\ConverterInterface|null
   */
  protected ?ConverterInterface $converter;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bpmn_io',
    'bpmn_io_test',
    'eca',
    'eca_base',
    'eca_content',
    'eca_modeller_bpmn',
    'eca_ui',
    'eca_user',
    'eca_views',
    'field',
    'user',
    'views',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->installConfig(static::$modules);

    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->converter = \Drupal::service('bpmn_io.services.converter');
  }

  /**
   * Convert an ECA-entity that uses the fallback-model.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testConvertFallback(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    // Confirm initial state.
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);

    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = $storage->load('eca_fallback');
    $this->assertEquals('fallback', $eca->getModeller()->getPluginId());

    // Convert.
    $build = $this->converter->convert($eca);

    // Assert result.
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);
    $this->assertEquals('bpmn_io', $eca->getModeller()->getPluginId());
    $this->assertCount(34, $build['#attached']['drupalSettings']['bpmn_io_convert']['elements']);
    $this->assertEquals('StartEvent', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Event_0erz1e4']);
    $this->assertEquals('ExclusiveGateway', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Gateway_1rthid4']);
    $this->assertEquals('SequenceFlow', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Flow_0a1zeo8']);
    $this->assertEquals('Task', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Activity_0tlx3ln']);
    $this->assertEquals('event', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Event_04tl9lk']);
    $this->assertEquals('condition', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Flow_0c7hrjx']);
    $this->assertEquals('action', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Activity_0xd3fam']);
  }

  /**
   * Convert an ECA-entity that uses a non fallback-model.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testConvertNonFallback(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    // Confirm initial state.
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);

    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = $storage->load('eca_bpmn_io');
    $this->assertEquals('bpmn_io', $eca->getModeller()->getPluginId());

    // Convert.
    $build = $this->converter->convert($eca);

    // Assert the original entity.
    $this->assertEquals('bpmn_io', $eca->getModeller()->getPluginId());
    /** @var \Drupal\eca\Entity\Eca[] $ecaCollection */
    $ecaCollection = $storage->loadMultiple();
    $this->assertCount(2, $ecaCollection);

    $this->assertStringNotContainsString('(clone)', $build['#attached']['drupalSettings']['bpmn_io_convert']['metadata']['name']);
    /** @var \Drupal\eca\Entity\Eca[] $result */
    $result = $storage->loadByProperties(['label' => $build['#attached']['drupalSettings']['bpmn_io_convert']['metadata']['name']]);
    $this->assertEquals($eca->id(), reset($result)->id());
    $this->assertStringContainsString(reset($result)->id(), $build['#attached']['drupalSettings']['bpmn_io_convert']['metadata']['redirect_url']);

    // Assert build.
    $this->assertCount(34, $build['#attached']['drupalSettings']['bpmn_io_convert']['elements']);
    $this->assertEquals('StartEvent', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Event_00dfxlw']);
    $this->assertEquals('ExclusiveGateway', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Gateway_0hd8858']);
    $this->assertEquals('SequenceFlow', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Flow_1vczt3y']);
    $this->assertEquals('Task', $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping']['Activity_0nr4ng5']);
    $this->assertEquals('event', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Event_0erz1e4']);
    $this->assertEquals('condition', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Flow_0xavi4t']);
    $this->assertEquals('action', $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping']['Activity_0tlx3ln']);
  }

}
