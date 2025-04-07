<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\node\Entity\Node;

/**
 * Kernel tests regarding ECA render Serialize action.
 *
 * @group eca
 * @group eca_render
 */
class SerializeTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_serialize".
   */
  public function testSerialize(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Serialize $action */
    $action = $this->actionManager->createInstance('eca_render_serialize:serialization', [
      'format' => 'json',
      'value' => '[node]',
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

    $node = Node::create([
      'title' => $this->randomMachineName(),
      'body' => $this->randomMachineName(),
      'type' => 'article',
      'status' => TRUE,
    ]);
    $node->save();
    $this->tokenService->addTokenData('node', $node);

    $this->dispatchBasicRenderEvent([]);
    $this->assertEquals(\Drupal::service('serializer')->serialize($node, 'json'), trim((string) $build[0]['#serialized']));
  }

}
