<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render GetFileContents action.
 *
 * @group eca
 * @group eca_render
 */
class GetFileContentsTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_file_contents".
   */
  public function testGetFileContents(): void {
    /** @var \Drupal\eca_render\Plugin\Action\GetFileContents $action */
    $action = $this->actionManager->createInstance('eca_render_file_contents', [
      'uri' => 'data:text/plain;base64,' . base64_encode('Hello'),
      'encoding' => 'string:raw',
      'token_mime_type' => '',
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
    $this->assertSame('Hello', $build[0]['#markup']);
  }

}
