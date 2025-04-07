<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;

/**
 * Routing event tests provided by "eca_misc".
 *
 * @group eca
 * @group eca_misc
 */
class RoutingEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_misc',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(static::$modules);
  }

  /**
   * Tests proper instantiation.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testProperInstantiation(): void {
    /** @var \Drupal\eca\PluginManager\Event $eventManager */
    $eventManager = \Drupal::service('plugin.manager.eca.event');

    /** @var \Drupal\eca_misc\Plugin\ECA\Event\RoutingEvent$event */
    $event = $eventManager->createInstance('routing:alter', []);
    $this->assertEquals('routing', $event->getBaseId());
  }

  /**
   * Tests reacting upon routing events.
   */
  public function testRoutingEvents(): void {
    // This config does the following:
    // 1. It reacts upon all routing events.
    // 2. It increments an array entry for each triggered event.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_routing_events',
      'label' => 'ECA routing events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'alter' => [
          'plugin' => 'routing:alter',
          'label' => 'routing alter',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_alter', 'condition' => ''],
          ],
        ],
        'dynamic' => [
          'plugin' => 'routing:dynamic',
          'label' => 'routing dynamic',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_dynamic', 'condition' => ''],
          ],
        ],
        'finished' => [
          'plugin' => 'routing:finished',
          'label' => 'routing finished',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_finished', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'increment_alter' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'alter',
          'configuration' => [
            'key' => 'alter',
          ],
          'successors' => [],
        ],
        'increment_dynamic' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'dynamic',
          'configuration' => [
            'key' => 'dynamic',
          ],
          'successors' => [],
        ],
        'increment_finished' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'finished',
          'configuration' => [
            'key' => 'finished',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\Core\Routing\RouteBuilder $route_builder */
    $route_builder = \Drupal::service('router.builder');
    $route_builder->rebuild();
    $this->assertSame(1, ArrayIncrement::$array['alter']);
    $this->assertSame(1, ArrayIncrement::$array['dynamic']);
    $this->assertSame(1, ArrayIncrement::$array['finished']);
  }

}
