<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca_content\Event\ContentEntityCustomEvent;
use Drupal\eca_content\Event\ContentEntityEvents;
use Drupal\eca_content\Event\ContentEntityPreSave;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_trigger_content_entity_custom_event" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class TriggerContentEntityCustomEventTest extends KernelTestBase {

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
    'filter',
    'text',
    'node',
    'eca',
    'eca_content',
  ];

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node = NULL;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    // Create an article content type.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => FALSE,
    ]);
    $node_type->save();
    $node = Node::create([
      'type' => 'article',
      'title' => 'A title',
      'uid' => 0,
      'status' => 1,
    ]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Tests triggering an entity-aware custom event.
   */
  public function testTriggerAction() {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    /** @var \Drupal\eca_content\Plugin\Action\TriggerContentEntityCustomEvent $action */
    $action = $action_manager->createInstance('eca_trigger_content_entity_custom_event', [
      'event_id' => 'my_custom_event',
      'tokens' => '',
    ]);
    // Fake an origin by using the presave event.
    $action->setEvent(new ContentEntityPreSave($this->node, _eca_content_entity_types()));
    $this->assertFalse($action->access(NULL), 'Access must be revoked when no entity is provided.');
    $this->assertTrue($action->access($this->node), 'Access must be granted when an entity is provided.');

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $received_event = NULL;
    $event_dispatcher->addListener(ContentEntityEvents::CUSTOM, static function ($event) use (&$received_event) {
      $received_event = $event;
    });

    $action->execute($this->node);
    $this->assertNotNull($received_event);
    $this->assertTrue($received_event instanceof ContentEntityCustomEvent);
    /** @var \Drupal\eca_content\Event\ContentEntityCustomEvent $received_event */
    $this->assertSame($this->node, $received_event->getEntity());

    // Now test with additional Tokens to forward.
    $token_services->addTokenData('my_tokens_1', $this->node);
    $token_services->addTokenData('my_tokens_2', [1, 2]);
    $token_services->addTokenData('my_tokens_3', 'I will not be forwarded.');
    /** @var \Drupal\eca_content\Plugin\Action\TriggerContentEntityCustomEvent $action */
    $action = $action_manager->createInstance('eca_trigger_content_entity_custom_event', [
      'event_id' => 'my_custom_event',
      'tokens' => 'my_tokens_1, my_tokens_2',
    ]);
    // Fake an origin by using the presave event.
    $action->setEvent(new ContentEntityPreSave($this->node, _eca_content_entity_types()));
    $this->assertFalse($action->access(NULL), 'Access must be revoked when no entity is provided.');
    $this->assertTrue($action->access($this->node), 'Access must be granted when an entity is provided.');

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $received_event = NULL;
    $event_dispatcher->addListener(ContentEntityEvents::CUSTOM, static function ($event) use (&$received_event) {
      $received_event = $event;
    });

    $action->execute($this->node);
    $this->assertNotNull($received_event);
    $this->assertTrue($received_event instanceof ContentEntityCustomEvent);
    /** @var \Drupal\eca_content\Event\ContentEntityCustomEvent $received_event */
    $this->assertSame($this->node, $received_event->getEntity());
    $this->assertSame($this->node, $token_services->getTokenData('my_tokens_1'));
    $this->assertTrue($token_services->hasTokenData('my_tokens_2'));
    $this->assertTrue($token_services->hasTokenData('my_tokens_3'));
    $this->assertSame(['my_tokens_1', 'my_tokens_2'], array_values($received_event->getTokenNamesToReceive()));
  }

}
