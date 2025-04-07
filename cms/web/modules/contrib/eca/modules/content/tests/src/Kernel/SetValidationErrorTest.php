<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca_content\Event\ContentEntityCreate;
use Drupal\eca_content\Event\ContentEntityEvents;
use Drupal\eca_content\Event\ContentEntityValidate;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_content_validation_error" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class SetValidationErrorTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'eca',
    'eca_content',
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
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
  }

  /**
   * Tests validating a node and check its violations.
   */
  public function testViolations() {

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $event_dispatcher = \Drupal::service('event_dispatcher');

    /** @var \Drupal\eca_content\Plugin\Action\SetValidationError $action */
    $action = $action_manager->createInstance('eca_content_validation_error', [
      'message' => 'There is an error for: [entity:title]',
      'property' => '[field]',
    ]);

    $token_services->addTokenData('entity:title', 'My first article');
    $token_services->addTokenData('field', 'title');

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'uid' => 0,
      'title' => 'My first article',
    ]);

    $event_dispatcher->addListener(ContentEntityEvents::VALIDATE, function (ContentEntityValidate $event) use ($node, &$action) {
      $action->setEvent($event);
      $this->assertTrue($action->access($node), 'Execution of this action is just permitted for content_entity:validate event.');
      $action->execute($node);
    });

    $violationList = $node->validate();
    $this->assertEquals(1, $violationList->count());
    $constraintViolation = $violationList->get(0);
    $this->assertEquals('There is an error for: My first article', $constraintViolation->getMessage());
    $this->assertEquals('title', $constraintViolation->getPropertyPath());
  }

  /**
   * Tests validating a node but use another event.
   */
  public function testNoViolations() {

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    /** @var \Drupal\eca_content\Plugin\Action\SetValidationError $action */
    $action = $action_manager->createInstance('eca_content_validation_error', [
      'message' => 'There is an error for: [entity:title]',
      'property' => '[field]',
    ]);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'uid' => 0,
      'title' => 'My second article',
    ]);

    $event_dispatcher->addListener(ContentEntityEvents::CREATE, function (ContentEntityCreate $event) use ($node, &$action) {
      $action->setEvent($event);
      $this->assertFalse($action->access($node), 'Execution of this action is not permitted for events other than content_entity:validate.');
      $action->execute($node);
    });

    $violationList = $node->validate();
    $this->assertEquals(0, $violationList->count());
  }

}
