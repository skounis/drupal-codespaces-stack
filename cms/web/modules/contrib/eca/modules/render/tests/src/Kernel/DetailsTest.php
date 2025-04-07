<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Details action.
 *
 * @group eca
 * @group eca_render
 */
class DetailsTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_details".
   */
  public function testDetails(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Details $action */
    $action = $this->actionManager->createInstance('eca_render_details', [
      'title' => 'Hello',
      'open' => TRUE,
      'introduction_text' => 'Introduction',
      'summary_value' => 'Summary...',
      'name' => 'mydetails',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'set:clear',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $this->dispatchBasicRenderEvent();

    $this->assertTrue(isset($build['mydetails']));
    $this->assertSame('details', $build['mydetails']['#type']);
    $this->assertSame('Introduction', $build['mydetails']['introduction_text']['#markup']);
    $this->assertSame('Summary...', $build['mydetails']['#value']);
  }

}
