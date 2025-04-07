<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render ResponsiveImage action.
 *
 * @group eca
 * @group eca_render
 */
class ResponsiveImageTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_responsive_image".
   */
  public function testResponsiveImage(): void {
    /** @var \Drupal\eca_render\Plugin\Action\ResponsiveImage $action */
    $action = $this->actionManager->createInstance('eca_render_responsive_image:responsive_image', [
      'uri' => '/core/themes/bartik/logo.svg',
      'style_name' => 'test',
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
    $this->assertSame('responsive_image', $build[0]['#type']);
    $this->assertSame('test', $build[0]['#responsive_image_style_id']);
  }

}
