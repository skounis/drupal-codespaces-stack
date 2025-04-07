<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Markup action.
 *
 * @group eca
 * @group eca_render
 */
class MarkupTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_markup".
   */
  public function testMarkup(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Markup $action */
    $action = $this->actionManager->createInstance('eca_render_markup', [
      'value' => '[build]',
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

    $token_build = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Hello from ECA',
      '#weight' => 100,
    ];
    $this->tokenService->addTokenData('build', $token_build);

    $this->dispatchBasicRenderEvent([]);
    $this->assertSame('<div>Hello from ECA</div>', trim((string) $build[0]['#markup']));
  }

}
