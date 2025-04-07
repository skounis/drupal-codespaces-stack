<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eca\Service\ServiceTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the service trait.
 *
 * @group eca
 */
class ServiceTraitTest extends UnitTestCase {

  use ServiceTrait;

  /**
   * Tests the sort of plugins.
   */
  public function testSortPlugins(): void {
    $plugins = [];
    foreach ([
      'testPluginB',
      'testPluginC',
      'testPluginA',
      'testPluginC',
    ] as $label) {
      $plugins[] = $this->getPluginMock($label);
    }
    $this->sortPlugins($plugins);
    foreach ([
      'testPluginA',
      'testPluginB',
      'testPluginC',
      'testPluginC',
    ] as $key => $label) {
      $this->assertEquals($label, $plugins[$key]->getPluginDefinition()['label']);
    }
  }

  /**
   * Gets plugin mocks by the given label.
   *
   * @param string $label
   *   The plugin label.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mocked plugin.
   */
  private function getPluginMock(string $label): MockObject {
    $mockObject = $this->createMock(PluginInspectionInterface::class);
    $mockObject->method('getPluginDefinition')->willReturn([
      'label' => $label,
    ]);
    return $mockObject;
  }

  /**
   * Tests the method fieldLabel with NULL value.
   */
  public function testFieldLabelWithNull(): void {
    $this->assertEquals('A test key',
      self::convertKeyToLabel('a_test_key'));
  }

}
