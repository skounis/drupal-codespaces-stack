<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\CustomEvent;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\user\Entity\User;

/**
 * Kernel tests for events provided by "eca_base".
 *
 * @group eca
 * @group eca_base
 */
class BaseEventsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_test_array',
    'eca_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests reacting upon events provided by "eca_base".
   */
  public function testBaseEvents(): void {
    // This config does the following:
    // 1. It reacts upon all base events
    // 2. It increments an array entry for each triggered event.
    $future_day = (date('w', (new \DateTime())->getTimestamp()) + 2) % 7;
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_base_events',
      'label' => 'ECA base events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'cron_every_time' => [
          'plugin' => 'eca_base:eca_cron',
          'label' => 'Cron event that should always match up.',
          'configuration' => [
            'frequency' => '* * * * *',
          ],
          'successors' => [
            ['id' => 'increment', 'condition' => ''],
          ],
        ],
        'cron_never' => [
          'plugin' => 'eca_base:eca_cron',
          'label' => 'Cron event that should never match up.',
          'configuration' => [
            'frequency' => '0 1 * * ' . $future_day,
          ],
          'successors' => [
            ['id' => 'increment', 'condition' => ''],
          ],
        ],
        'custom_specified' => [
          'plugin' => 'eca_base:eca_custom',
          'label' => 'Custom event using a specified ID.',
          'configuration' => [
            'event_id' => 'my_custom_event',
          ],
          'successors' => [
            ['id' => 'increment', 'condition' => ''],
          ],
        ],
        'custom_unspecified' => [
          'plugin' => 'eca_base:eca_custom',
          'label' => 'Custom event without specifying an ID.',
          'configuration' => [
            'event_id' => '',
          ],
          'successors' => [
            ['id' => 'increment', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'increment' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'base_inc',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    unset(ArrayIncrement::$array['base_inc']);

    /** @var \Drupal\Core\Cron $cron */
    $cron = \Drupal::service('cron');
    $cron->run();
    $this->assertSame(1, ArrayIncrement::$array['base_inc'], "Only one event must match up as configured.");

    ArrayIncrement::$array['base_inc'] = 0;

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    ArrayIncrement::$array['base_inc'] = 0;
    $event_dispatcher->dispatch(new CustomEvent('my_custom_event'), BaseEvents::CUSTOM);
    $this->assertSame(2, ArrayIncrement::$array['base_inc'], "Exactly two events must match up as configured.");

    ArrayIncrement::$array['base_inc'] = 0;
    $event_dispatcher->dispatch(new CustomEvent('another_custom_event'), BaseEvents::CUSTOM);
    $this->assertSame(1, ArrayIncrement::$array['base_inc'], "Only one event must match up as configured.");
  }

}
