<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Cacheability action.
 *
 * @group eca
 * @group eca_render
 */
class CacheabilityTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_cacheability".
   */
  public function testCacheability(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Cacheability $action */
    $action = $this->actionManager->createInstance('eca_render_cacheability', [
      'cache_type' => 'tags',
      'cache_value' => 'node:list',
      'name' => '',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'append',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $event_build = [
      '#type' => 'markup',
      '#markup' => "Hello from ECA",
      '#weight' => 100,
    ];
    $this->dispatchBasicRenderEvent($event_build);

    $event_build['#cache'] = [
      'tags' => ['node:list'],
    ];

    $this->assertSame($event_build, $build);
  }

}
