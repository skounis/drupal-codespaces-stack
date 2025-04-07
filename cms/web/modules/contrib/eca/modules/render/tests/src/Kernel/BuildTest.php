<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Render\Element;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Build action.
 *
 * @group eca
 * @group eca_render
 */
class BuildTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_build".
   *
   * Merge without name.
   */
  public function testMerge(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Build $action */
    $action = $this->actionManager->createInstance('eca_render_build', [
      'value' => '[build]',
      'use_yaml' => FALSE,
      'name' => '',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'merge',
    ]);

    $build = [
      '#type' => 'markup',
      '#markup' => "Hello I am a markup",
    ];

    $event = new BasicRenderEvent($build);
    $action->setEvent($event);

    $token_build = [
      '#type' => 'markup',
      '#weight' => "100",
    ];
    $this->tokenService->addTokenData('build', $token_build);
    $action->execute();
    $build = $event->getRenderArray();

    unset($build['#cache']);
    unset($build['#attached']);

    $this->assertSame([
      '#type' => 'markup',
      '#markup' => "Hello I am a markup",
      '#weight' => "100",
    ], $build);
  }

  /**
   * Tests the action plugin "eca_render_build".
   *
   * Merge with name, but the name does not exist in existing render array.
   */
  public function testMergeWithName(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Build $action */
    $action = $this->actionManager->createInstance('eca_render_build', [
      'value' => '[build]',
      'use_yaml' => FALSE,
      'name' => 'test',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'merge',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $token_build = [
      '#type' => 'markup',
      '#markup' => "Hello from ECA",
      '#weight' => "100",
    ];
    $this->tokenService->addTokenData('build', $token_build);

    $this->dispatchBasicRenderEvent();

    $build = array_intersect_key($build, array_flip(Element::children($build)));
    $this->assertSame(['test' => $token_build], $build);
  }

  /**
   * Tests the action plugin "eca_render_build".
   *
   *  Merge with name, with the name does exist in existing render array.
   */
  public function testMergeKeyDoesExist(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Build $action */
    $action = $this->actionManager->createInstance('eca_render_build', [
      'value' => '[build]',
      'use_yaml' => FALSE,
      'name' => 'test_key',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'merge',
    ]);

    $build = [
      'test_key' => [
        '#type' => 'markup',
        '#markup' => "Hello I am a markup",
      ],
    ];

    $event = new BasicRenderEvent($build);
    $action->setEvent($event);

    $token_build = [
      '#type' => 'markup',
      '#weight' => "100",
    ];
    $this->tokenService->addTokenData('build', $token_build);
    $action->execute();
    $build = $event->getRenderArray();

    $build = array_intersect_key($build, array_flip(Element::children($build)));
    $this->assertSame([
      'test_key' => [
        '#type' => 'markup',
        '#markup' => "Hello I am a markup",
        '#weight' => "100",
      ],
    ], $build);
  }

}
