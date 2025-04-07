<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Render\Element;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render Text action.
 *
 * @group eca
 * @group eca_render
 */
class TextTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_text".
   */
  public function testText(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Text $action */
    $action = $this->actionManager->createInstance('eca_render_text:filter', [
      'text' => '<h1>Hello from ECA</h1>',
      'format' => 'plain_text',
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

    $this->dispatchBasicRenderEvent();

    $build = array_intersect_key($build, array_flip(Element::children($build)));
    $this->assertSame('processed_text', $build[0]['#type']);
    $this->assertSame('plain_text', $build[0]['#format']);

    $rendered = trim((string) \Drupal::service('renderer')->renderInIsolation($build));
    $this->assertEquals('<p>&lt;h1&gt;Hello from ECA&lt;/h1&gt;</p>', $rendered);
  }

}
