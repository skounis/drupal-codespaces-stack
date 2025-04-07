<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render SetWeight action.
 *
 * @group eca
 * @group eca_render
 */
class SetWeightTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_set_weight".
   */
  public function testSetWeight(): void {
    /** @var \Drupal\eca_render\Plugin\Action\SetWeight $action */
    $action = $this->actionManager->createInstance('eca_render_set_weight', [
      'name' => 'some_key',
      'weight' => '107',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    $this->dispatchBasicRenderEvent([
      'some_key' => [
        '#type' => 'markup',
        '#markup' => "Hello from ECA",
        '#weight' => 100,
      ],
    ]);

    $this->assertSame(107, $build['some_key']['#weight']);
  }

}
