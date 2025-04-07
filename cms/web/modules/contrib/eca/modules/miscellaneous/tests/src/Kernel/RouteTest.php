<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_misc\Plugin\RouteInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for the "eca_route" submodule.
 *
 * This covers "eca_route_match" condition and "eca_token_load_route_param"
 * action plugins.
 *
 * @group eca
 * @group eca_misc
 */
class RouteTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'node',
    'text',
    'user',
    'eca',
    'eca_misc',
  ];

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * ECA model plugin manager.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    $this->node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => 'Route test article',
      'status' => 0,
    ]);
    $this->node->save();
    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests route condition.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testRouteConditions(): void {
    $route = 'user.login';
    /** @var \Drupal\Core\Routing\RouteMatchInterface|\Prophecy\Prophecy\ObjectProphecy $route_matcher */
    $route_matcher = $this->prophesize(RouteMatchInterface::class);
    $route_matcher->getRouteName()->willReturn($route);
    \Drupal::getContainer()->set('current_route_match', $route_matcher->reveal());

    $config = [
      'route' => $route,
      'request' => RouteInterface::ROUTE_CURRENT,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];

    /** @var \Drupal\eca_misc\Plugin\ECA\Condition\RouteMatch $condition */
    $condition = $this->conditionManager->createInstance('eca_route_match', $config);
    $this->assertTrue($condition->evaluate(), 'Route user.login get returned.');
  }

  /**
   * Tests route parameter action.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testRouteParameters(): void {
    $route = 'entity.node.canonical';

    /** @var \Drupal\Core\Routing\RouteMatchInterface|\Prophecy\Prophecy\ObjectProphecy $route_matcher */
    $route_matcher = $this->prophesize(RouteMatchInterface::class);
    $route_matcher->getRouteName()->willReturn($route);
    $route_matcher->getParameter('node')->willReturn($this->node);
    \Drupal::getContainer()->set('current_route_match', $route_matcher->reveal());

    $config = [
      'token_name' => 'mynode',
      'request' => RouteInterface::ROUTE_CURRENT,
      'parameter_name' => 'node',
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\TokenLoadRouteParameter $action */
    $action = $this->actionManager->createInstance('eca_token_load_route_param', $config);
    $action->execute();
    $this->assertSame($this->node, $this->tokenService->getTokenData('mynode'), 'The node entity has been added to the token system.');

    // Test access control for this action and its parameter.
    $user0 = User::load(0);
    $this->assertFalse($action->access(NULL, $user0), 'User 0 should not have access to the route parameter node.');
    $user1 = User::load(1);
    $this->assertTrue($action->access(NULL, $user1), 'User 1 should have access to the route parameter node.');
  }

}
