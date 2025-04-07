<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Dropbutton action.
 *
 * @group eca
 * @group eca_render
 */
class DropbuttonTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_custom_form".
   */
  public function testDropbutton(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Dropbutton $action */
    $action = $this->actionManager->createInstance('eca_render_dropbutton', [
      'dropbutton_type' => 'small',
      'links' => '[links]',
      'use_yaml' => FALSE,
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

    $this->tokenService->addTokenData('links', [
      ['title' => 'Structure', 'url' => '/admin/structure'],
      ['title' => 'Config', 'url' => '/admin/config'],
    ]);

    $this->dispatchBasicRenderEvent([]);

    $this->assertTrue(isset($build[0]));
    $this->assertSame('dropbutton', $build[0]['#type']);
    $this->assertSame('Structure', $build[0]['#links'][0]['title']);
    $this->assertSame('Config', $build[0]['#links'][1]['title']);
  }

}
