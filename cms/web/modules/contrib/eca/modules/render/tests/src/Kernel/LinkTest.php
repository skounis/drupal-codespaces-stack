<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Url;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Link action.
 *
 * @group eca
 * @group eca_render
 */
class LinkTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_link".
   */
  public function testLink(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Link $action */
    $action = $this->actionManager->createInstance('eca_render_link', [
      'title' => 'Structure',
      'url' => '/admin/structure',
      'link_type' => 'modal',
      'width' => '80',
      'display_as' => 'anchor',
      'absolute' => FALSE,
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
    $this->assertSame('link', $build[0]['#type']);
    $this->assertInstanceOf(Url::class, $build[0]['#url']);
    $this->assertEquals('Structure', $build[0]['#title']);
  }

}
