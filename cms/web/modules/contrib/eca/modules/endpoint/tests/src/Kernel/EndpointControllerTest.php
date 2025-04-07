<?php

namespace Drupal\Tests\eca_endpoint\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_endpoint\Controller\EndpointController;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Kernel tests regarding the ECA endpoint controller.
 *
 * @group eca
 * @group eca_endpoint
 */
class EndpointControllerTest extends KernelTestBase {

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
    'eca_access',
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

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);
  }

  /**
   * Tests the custom access callback of the endpoint controller.
   */
  public function testControllerAccess(): void {
    $controller = EndpointController::create($this->container);
    $result = $controller->access(User::load(0), 'first', 'second');
    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());

    $result = $controller->access(User::load(1), 'first', 'second');
    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());

    // This config does the following:
    // 1. It reacts upon endpoint access for "/eca/first/second" URL path.
    // 2. Upon that, it grants access for all users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_access',
      'label' => 'ECA endpoint access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_access' => [
          'plugin' => 'eca_endpoint:access',
          'label' => 'ECA endpoint access',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $result = $controller->access(User::load(0), 'first', 'second');
    $this->assertTrue($result->isAllowed());

    $result = $controller->access(User::load(1), 'first', 'second');
    $this->assertTrue($result->isAllowed());

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertTrue($result->isAllowed());

    $result = $controller->access(User::load(2), 'first', 'third');
    $this->assertFalse($result->isAllowed());
  }

  /**
   * Tests the handle callback of the endpoint controller.
   */
  public function testControllerHandle(): void {
    $controller = EndpointController::create($this->container);
    $request = Request::create('/eca/first/second');
    $request->setSession(new Session());

    $not_found = FALSE;
    try {
      $controller->handle($request, User::load(1), 'first', 'second');
    }
    catch (NotFoundHttpException $e) {
      $not_found = TRUE;
    }
    $this->assertTrue($not_found);

    // This config does the following:
    // 1. It reacts upon endpoint response for "/eca/first/second" URL path.
    // 2. Upon that, it writes a plain string into the response as content.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_response',
      'label' => 'ECA endpoint response',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_response' => [
          'plugin' => 'eca_endpoint:response',
          'label' => 'ECA endpoint response',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'set_content', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_content' => [
          'plugin' => 'eca_endpoint_set_response_content',
          'label' => 'Set content',
          'configuration' => [
            'content' => 'Hello from ECA!',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $not_found = FALSE;
    try {
      $controller->handle($request, User::load(1), 'first', 'third');
    }
    catch (NotFoundHttpException $e) {
      $not_found = TRUE;
    }
    $this->assertTrue($not_found, "Despite of being defined, the endpoint still returns a 404, because no access has been defined yet.");

    // This config does the following:
    // 1. It reacts upon endpoint access for "/eca/first/second" URL path.
    // 2. Upon that, it grants access for all users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_access',
      'label' => 'ECA endpoint access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_access' => [
          'plugin' => 'eca_endpoint:access',
          'label' => 'ECA endpoint access',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $response = $controller->handle($request, User::load(1), 'first', 'second');
    $this->assertEquals('Hello from ECA!', $response->getContent());

    $not_found = FALSE;
    try {
      $controller->handle($request, User::load(1), 'first', 'third');
    }
    catch (NotFoundHttpException $e) {
      $not_found = TRUE;
    }
    $this->assertTrue($not_found);
  }

}
