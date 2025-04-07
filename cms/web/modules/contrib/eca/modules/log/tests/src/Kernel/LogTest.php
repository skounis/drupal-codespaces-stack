<?php

namespace Drupal\Tests\eca_log\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_log\Event\LogMessageEvent;
use Drupal\eca_log\LogEvents;

/**
 * Kernel tests for events provided by "eca_log".
 *
 * @group eca
 * @group eca_log
 */
class LogTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_log',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
  }

  /**
   * Tests "eca_log" plugins.
   */
  public function testLog(): void {
    // This config does the following:
    // 1. It reacts upon the log message event.
    // 2. Upon that, it writes a message into the log of a different channel.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_log_process',
      'label' => 'ECA log process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'log_message_eca_log_test' => [
          'plugin' => 'log:log_message',
          'label' => 'Log message',
          'configuration' => [
            'channel' => 'eca_test',
            'min_severity' => RfcLogLevel::INFO,
          ],
          'successors' => [
            ['id' => 'write_log_eca_test2', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_log_eca_test2' => [
          'plugin' => 'eca_write_log_message',
          'label' => 'Write log message',
          'configuration' => [
            'channel' => 'eca_test2',
            'severity' => RfcLogLevel::INFO,
            'message' => 'Pong!',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $received = NULL;
    $event_dispatcher->addListener(LogEvents::MESSAGE, function (LogMessageEvent $event) use (&$received) {
      if ($event->getContext()['channel'] === 'eca_test2') {
        $received = $event->getMessage();
      }
    });

    \Drupal::logger('eca_test')->log(RfcLogLevel::INFO, 'Ping!');
    $this->assertEquals('Pong!', $received);
  }

}
