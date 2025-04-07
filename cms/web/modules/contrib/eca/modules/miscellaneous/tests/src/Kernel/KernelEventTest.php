<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Drupal\Core\DrupalKernelInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Kernel event tests provided by "eca_misc".
 *
 * @group eca
 * @group eca_misc
 */
class KernelEventTest extends KernelTestBase {

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

    /** @var \Drupal\eca_misc\Plugin\ECA\Event\KernelEvent $event */
    $event = $eventManager->createInstance('kernel:view', []);
    $this->assertEquals('kernel', $event->getBaseId());
  }

  /**
   * Tests reacting upon kernel events.
   */
  public function testKernelEvents(): void {
    // This config does the following:
    // 1. It reacts upon all kernel events.
    // 2. It increments an array entry for each triggered event.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_kernel_events',
      'label' => 'ECA kernel events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'view' => [
          'plugin' => 'kernel:view',
          'label' => 'kernel view',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_view', 'condition' => ''],
          ],
        ],
        'controller' => [
          'plugin' => 'kernel:controller',
          'label' => 'kernel controller',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_controller', 'condition' => ''],
          ],
        ],
        'controller_arguments' => [
          'plugin' => 'kernel:controller_arguments',
          'label' => 'kernel controller_arguments',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_controller_arguments', 'condition' => ''],
          ],
        ],
        'exception' => [
          'plugin' => 'kernel:exception',
          'label' => 'kernel exception',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_exception', 'condition' => ''],
          ],
        ],
        'finish_request' => [
          'plugin' => 'kernel:finish_request',
          'label' => 'kernel finish_request',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_finish_request', 'condition' => ''],
          ],
        ],
        'request' => [
          'plugin' => 'kernel:request',
          'label' => 'kernel request',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_request', 'condition' => ''],
            ['id' => 'write_request', 'condition' => ''],
          ],
        ],
        'response' => [
          'plugin' => 'kernel:response',
          'label' => 'kernel response',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_response', 'condition' => ''],
          ],
        ],
        'terminate' => [
          'plugin' => 'kernel:terminate',
          'label' => 'kernel terminate',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_terminate', 'condition' => ''],
          ],
        ],
        'container_initialize_subrequest_finished' => [
          'plugin' => 'kernel:container_initialize_subrequest_finished',
          'label' => 'kernel container_initialize_subrequest_finished',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_subrequest_finished', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'increment_view' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'view',
          'configuration' => [
            'key' => 'view',
          ],
          'successors' => [],
        ],
        'increment_controller' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'controller',
          'configuration' => [
            'key' => 'controller',
          ],
          'successors' => [],
        ],
        'increment_controller_arguments' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'controller_arguments',
          'configuration' => [
            'key' => 'controller_arguments',
          ],
          'successors' => [],
        ],
        'increment_exception' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'exception',
          'configuration' => [
            'key' => 'exception',
          ],
          'successors' => [],
        ],
        'increment_finish_request' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'finish_request',
          'configuration' => [
            'key' => 'finish_request',
          ],
          'successors' => [],
        ],
        'increment_request' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'request increment',
          'configuration' => [
            'key' => 'request',
          ],
          'successors' => [],
        ],
        'write_request' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'request write',
          'configuration' => [
            'key' => 'request',
            'value' => 'request [event:machine_name] [event:method]',
          ],
          'successors' => [],
        ],
        'increment_response' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'response',
          'configuration' => [
            'key' => 'response',
          ],
          'successors' => [],
        ],
        'increment_terminate' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'terminate',
          'configuration' => [
            'key' => 'terminate',
          ],
          'successors' => [],
        ],
        'increment_subrequest_finished' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'container_initialize_subrequest_finished',
          'configuration' => [
            'key' => 'container_initialize_subrequest_finished',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $event_dispatcher->dispatch(new Event(), DrupalKernelInterface::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED);
    $this->assertSame(1, ArrayIncrement::$array['container_initialize_subrequest_finished']);

    $kernel = $this->container->get('kernel');
    $request = Request::create('http://www.example.local');
    $response = new Response();
    $event_dispatcher->dispatch(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);
    $this->assertSame(1, ArrayIncrement::$array['request']);
    $this->assertSame('request kernel.request GET', ArrayWrite::$array['request']);

    $controller = function () {};
    $event = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
    $event_dispatcher->dispatch($event, KernelEvents::CONTROLLER);
    $controller = $event->getController();
    $this->assertSame(1, ArrayIncrement::$array['controller']);

    $event = new ControllerArgumentsEvent($kernel, $controller, [], $request, HttpKernelInterface::MAIN_REQUEST);
    $event_dispatcher->dispatch($event, KernelEvents::CONTROLLER_ARGUMENTS);
    $this->assertSame(1, ArrayIncrement::$array['controller_arguments']);

    $event_dispatcher->dispatch(new FinishRequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::FINISH_REQUEST);
    $this->assertSame(1, ArrayIncrement::$array['finish_request']);

    $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    $event_dispatcher->dispatch($event, KernelEvents::VIEW);
    $this->assertSame(1, ArrayIncrement::$array['view']);

    $response_event = new ResponseEvent(\Drupal::service('http_kernel'), Request::createFromGlobals(), HttpKernelInterface::MAIN_REQUEST, $response);
    $event_dispatcher->dispatch($response_event, KernelEvents::RESPONSE);
    $this->assertSame(1, ArrayIncrement::$array['response']);

    $e = new MethodNotAllowedHttpException(['POST', 'PUT'], 'test');
    $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $event_dispatcher->dispatch($event, KernelEvents::EXCEPTION);
    $this->assertSame(1, ArrayIncrement::$array['exception']);

    $event = new TerminateEvent($kernel, $request, $response);
    $event_dispatcher->dispatch($event, KernelEvents::TERMINATE);
    $this->assertSame(1, ArrayIncrement::$array['terminate']);
  }

}
