<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Image action.
 *
 * @group eca
 * @group eca_render
 */
class ImageTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_image".
   */
  public function testImage(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Image $action */
    $action = $this->actionManager->createInstance('eca_render_image:image', [
      'uri' => '/core/themes/bartik/logo.svg',
      'style_name' => '',
      'alt' => '',
      'title' => '',
      'width' => '',
      'height' => '',
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
    $this->assertSame('/core/themes/bartik/logo.svg', $build[0]['#uri']);
  }

}
