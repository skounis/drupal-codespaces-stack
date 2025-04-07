<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Kernel tests regarding ECA render Unserialize action.
 *
 * @group eca
 * @group eca_render
 */
class UnserializeTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_unserialize".
   */
  public function testUnserialize(): void {
    $title = $this->randomMachineName();
    $node = Node::create([
      'title' => $title,
      'body' => $this->randomMachineName(),
      'type' => 'article',
      'status' => TRUE,
    ]);
    $node->save();

    /** @var \Drupal\eca_render\Plugin\Action\Unserialize $action */
    $action = $this->actionManager->createInstance('eca_render_unserialize:serialization', [
      'format' => 'json',
      'value' => \Drupal::service('serializer')->serialize($node, 'json'),
      'type' => 'node',
      'use_yaml' => FALSE,
      'token_name' => '',
      'name' => '',
      'weight' => '100',
      'mode' => 'append',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $this->dispatchBasicRenderEvent([]);
    $this->assertInstanceOf(NodeInterface::class, $build[0]['#data']);
    $this->assertEquals($title, $build[0]['#data']->title->value);
  }

}
