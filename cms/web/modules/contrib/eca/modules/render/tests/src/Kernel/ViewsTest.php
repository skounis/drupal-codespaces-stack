<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Component\Render\MarkupInterface;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\views\Entity\View;

/**
 * Kernel tests regarding ECA render Views action.
 *
 * @group eca
 * @group eca_render
 */
class ViewsTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_views".
   */
  public function testViews(): void {
    View::create([
      'id' => 'test_view',
      'label' => 'Test View',
    ])->save();

    /** @var \Drupal\eca_render\Plugin\Action\Views $action */
    $action = $this->actionManager->createInstance('eca_render_views:views', [
      'view_id' => 'test_view',
      'display_id' => 'default',
      'arguments' => '',
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
    $this->assertInstanceOf(MarkupInterface::class, $build[0]['#markup']);
  }

}
