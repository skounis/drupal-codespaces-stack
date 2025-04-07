<?php

namespace Drupal\Tests\eca_endpoint\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_endpoint\EndpointEvents;
use Drupal\eca_endpoint\Event\EndpointResponseEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Kernel tests regarding ECA endpoint actions.
 *
 * @group eca
 * @group eca_endpoint
 */
class EndpointActionsTest extends KernelTestBase {

  /**
   * Core action manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
   */
  protected ?EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'node',
    'eca',
    'eca_endpoint',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'auth'])->save();

    $request = Request::create('/eca/first/second?a=b', 'POST', [], [], [], [], 'hello');
    $request->setSession(new Session());
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
    $this->eventDispatcher = \Drupal::service('event_dispatcher');
  }

  /**
   * Tests the action plugin "eca_endpoint_get_client_ip".
   */
  public function testGetClientIp(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetClientIP $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_client_ip', [
      'token_name' => 'the_client_ip',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('the_client_ip'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('the_client_ip'));
    $this->assertEquals(\Drupal::requestStack()->getCurrentRequest()->getClientIp(), $this->tokenService->getTokenData('the_client_ip'));
  }

  /**
   * Tests the action plugin "eca_endpoint_get_path_argument".
   */
  public function testGetPathArgument(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetPathArgument $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_path_argument', [
      'index' => '1',
      'token_name' => 'path_arg',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('path_arg'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('path_arg'));
    $this->assertEquals('first', $this->tokenService->getTokenData('path_arg'));
  }

  /**
   * Tests the action plugin "eca_endpoint_get_query_parameter".
   */
  public function testGetQueryParameter(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetQueryParameter $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_query_parameter', [
      'name' => 'a',
      'token_name' => 'query_param',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('query_param'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('query_param'));
    $this->assertEquals('b', $this->tokenService->getTokenData('query_param'));
  }

  /**
   * Tests the action plugin "eca_endpoint_get_request_content".
   */
  public function testGetRequestContent(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetRequestContent $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_request_content', [
      'token_name' => 'content',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('content'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('content'));
    $this->assertEquals('hello', $this->tokenService->getTokenData('content'));
  }

  /**
   * Tests the action plugin "eca_endpoint_get_request_content_type".
   */
  public function testGetRequestContentType(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetRequestContentType $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_request_content_type', [
      'token_name' => 'type',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('type'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('type'));
    $this->assertEquals('form', $this->tokenService->getTokenData('type'), "A POST request will default return form as content type.");
  }

  /**
   * Tests the action plugin "eca_endpoint_get_request_header".
   */
  public function testGetRequestHeader(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetRequestHeader $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_request_header', [
      'name' => 'content-type',
      'token_name' => 'headers',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('headers'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('headers'));
    $this->assertEquals('application/x-www-form-urlencoded', $this->tokenService->getTokenData('headers'), "A POST request will default return form as content-type header.");
  }

  /**
   * Tests the action plugin "eca_endpoint_get_request_method".
   */
  public function testGetRequestMethod(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\GetRequestMethod $action */
    $action = $this->actionManager->createInstance('eca_endpoint_get_request_method', [
      'token_name' => 'method',
    ]);
    $this->assertTrue(!$this->tokenService->hasTokenData('method'));

    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action) {
      $action->setEvent($event);
      $action->execute();
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($this->tokenService->hasTokenData('method'));
    $this->assertEquals('POST', $this->tokenService->getTokenData('method'));
  }

  /**
   * Tests the action plugin "eca_endpoint_set_response_content".
   */
  public function testSetResponseContent(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\SetResponseContent $action */
    $action = $this->actionManager->createInstance('eca_endpoint_set_response_content', [
      'content' => 'Hello again!',
    ]);

    $response = NULL;
    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action, &$response) {
      $action->setEvent($event);
      $action->execute();
      $response = $event->response;
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($response instanceof Response);
    if ($response instanceof Response) {
      $this->assertEquals('Hello again!', $response->getContent());
    }
  }

  /**
   * Tests the action plugin "eca_endpoint_set_response_content_type".
   */
  public function testSetResponseContentType(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\SetResponseContentType $action */
    $action = $this->actionManager->createInstance('eca_endpoint_set_response_content_type', [
      'content_type' => 'text/plain; charset=UTF-7',
    ]);

    $response = NULL;
    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action, &$response) {
      $action->setEvent($event);
      $action->execute();
      $response = $event->response;
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($response instanceof Response);
    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $this->assertEquals('text/plain; charset=UTF-7', $response->headers->get('Content-Type'));
  }

  /**
   * Tests the action plugin "eca_endpoint_set_response_expires".
   */
  public function testSetResponseExpires(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\SetResponseExpires $action */
    $action = $this->actionManager->createInstance('eca_endpoint_set_response_expires', [
      'expires' => '1662552364',
    ]);

    $response = NULL;
    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action, &$response) {
      $action->setEvent($event);
      $action->execute();
      $response = $event->response;
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($response instanceof Response);
    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $this->assertEquals('1662552364', (string) $response->getExpires()->getTimestamp());
  }

  /**
   * Tests the action plugin "eca_endpoint_set_response_headers".
   */
  public function testSetResponseHeaders(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\SetResponseHeaders $action */
    $action = $this->actionManager->createInstance('eca_endpoint_set_response_headers', [
      'headers' => '[headers]',
      'use_yaml' => FALSE,
    ]);

    $response = NULL;
    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action, &$response) {
      $action->setEvent($event);
      $action->execute();
      $response = $event->response;
    });

    $this->tokenService->addTokenData('headers', [
      'X-ECA-Test' => 'Kernel',
    ]);
    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($response instanceof Response);
    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $this->assertEquals('Kernel', (string) $response->headers->get('X-ECA-Test'));
  }

  /**
   * Tests the action plugin "eca_endpoint_set_response_max_age".
   */
  public function testSetResponseMaxAge(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\SetResponseHeaders $action */
    $action = $this->actionManager->createInstance('eca_endpoint_set_response_max_age', [
      'max_age' => '300',
      's_max_age' => '400',
      'set_public' => TRUE,
      'set_expires' => TRUE,
    ]);

    $response = NULL;
    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action, &$response) {
      $action->setEvent($event);
      $action->execute();
      $response = $event->response;
    });
    $timestamp = (new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp();
    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($response instanceof Response);
    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $this->assertTrue($response->getExpires()->getTimestamp() - $timestamp >= 300);
    $this->assertTrue($response->getExpires()->getTimestamp() - $timestamp < 400);
    $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('s-maxage=400', $response->headers->get('Cache-Control'));
  }

  /**
   * Tests the action plugin "eca_endpoint_set_response_status_code".
   */
  public function testSetResponseStatusCode(): void {
    /** @var \Drupal\eca_endpoint\Plugin\Action\SetResponseStatusCode $action */
    $action = $this->actionManager->createInstance('eca_endpoint_set_response_status_code', [
      'code' => '202',
    ]);

    $response = NULL;
    $this->eventDispatcher->addListener(EndpointEvents::RESPONSE, function (EndpointResponseEvent $event) use (&$action, &$response) {
      $action->setEvent($event);
      $action->execute();
      $response = $event->response;
    });

    $this->dispatchEndpointResponseEvent();

    $this->assertTrue($response instanceof Response);
    /** @var \Symfony\Component\HttpFoundation\Response $response */
    $this->assertEquals(202, $response->getStatusCode());
  }

  /**
   * Dispatches an endpoint response event.
   */
  protected function dispatchEndpointResponseEvent(): void {
    $path_arguments = ['first', 'second'];
    $request = \Drupal::requestStack()->getCurrentRequest();
    $response = new Response();
    $account = \Drupal::currentUser();
    $build = [];
    $event = new EndpointResponseEvent($path_arguments, $request, $response, $account, $build);
    $this->eventDispatcher->dispatch($event, EndpointEvents::RESPONSE);
  }

}
