<?php

namespace Drupal\Tests\eca_views\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;

/**
 * Kernel tests for the "eca_views" submodule.
 *
 * @group eca
 * @group eca_views
 */
class ViewsQueryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'language',
    'content_translation',
    'node',
    'serialization',
    'rest',
    'views',
    'eca',
    'eca_views',
  ];

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * The user.
   *
   * @var \Drupal\Core\Session\AccountInterface|null
   */
  protected ?AccountInterface $user;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

  /**
   * The node.
   *
   * @var \Drupal\views\Entity\View|null
   */
  protected ?View $view;

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
    $this->user = User::create(['uid' => 0, 'name' => 'guest']);
    $this->user->save();
    $this->view = View::create([
      'id' => 'test_view',
      'label' => 'Test View',
    ]);
    $this->view->save();

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');

    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $translationManager */
    $translationManager = \Drupal::service('content_translation.manager');
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();

    $this->node = Node::create([
      'uid' => 1,
      'title' => 'First article',
      'type' => 'article',
      'langcode' => 'en',
    ]);
    $this->node->addTranslation('de', [
      'title' => 'German title',
      'body' => [
        'value' => $this->randomMachineName(),
        'format' => $this->randomMachineName(),
      ],
    ]);
    $this->node->save();

    $translationManager->setEnabled('node', 'article', TRUE);
  }

  /**
   * Tests views query.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testViewsQuery(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsQuery $viewsQuery */
    $viewsQuery = $this->actionManager->createInstance('eca_views_query', [
      'view_id' => 'test_view',
      'display_id' => 'default',
      'arguments' => 'a/b',
      'token_name' => 'my_custom_token:value1',
    ]);

    $viewsQuery->execute();
    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $this->tokenService->getTokenData('my_custom_token');
    /** @var \Drupal\node\NodeInterface $expectedNode */
    $expectedNode = $dto->getValue()['values']['value1'][0];
    $this->assertEquals($this->node->id(), $expectedNode->id());
  }

  /**
   * Tests with no token name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutTokenName(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsQuery $viewsQuery */
    $viewsQuery = $this->actionManager->createInstance('eca_views_query', [
      'view_id' => 'test_view',
      'display_id' => 'default',
      'arguments' => 'a/b',
    ]);

    $viewsQuery->execute();
    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $this->tokenService->getTokenData('eca');
    /** @var \Drupal\node\NodeInterface $expectedNode */
    $expectedNode = $dto->getValue()['values']['view']['values']['test_view']['values']['default'][0];
    $this->assertEquals($this->node->id(), $expectedNode->id());
  }

  /**
   * Tests with wrong view ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithWrongView(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsQuery $viewsQuery */
    $viewsQuery = $this->actionManager->createInstance('eca_views_query', [
      'view_id' => 'wrong_view',
      'display_id' => 'default',
      'token_name' => 'test',
    ]);
    $viewsQuery->execute();
    $this->assertNull($this->tokenService->getTokenData('test'));
  }

  /**
   * Tests with no view and display ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutViewAndDisplay(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsQuery $viewsQuery */
    $viewsQuery = $this->actionManager->createInstance('eca_views_query', [
      'token_name' => 'test',
    ]);
    $viewsQuery->execute();
    $this->assertNull($this->tokenService->getTokenData('test'));
  }

  /**
   * Test access with no display.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessWithoutDisplay(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsQuery $viewsQuery */
    $viewsQuery = $this->actionManager->createInstance('eca_views_query', [
      'token_name' => 'test',
    ]);

    $this->assertFalse($viewsQuery->access($this->node, $this->user));
  }

  /**
   * Test access with no user.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessWithoutUser(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsQuery $viewsQuery */
    $viewsQuery = $this->actionManager->createInstance('eca_views_query', [
      'view_id' => 'test_view',
      'display_id' => 'default',
    ]);
    $this->assertTrue($viewsQuery->access($this->node));
  }

}
