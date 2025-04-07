<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;

/**
 * Kernel tests regarding ECA render SetActiveTheme action.
 *
 * @group eca
 * @group eca_render
 */
class SetActiveThemeTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_set_active_theme".
   */
  public function testSetActiveTheme(): void {
    /** @var \Drupal\eca_render\Plugin\Action\SetActiveTheme $action */
    $action = $this->actionManager->createInstance('eca_set_active_theme', [
      'theme_name' => 'claro',
    ]);

    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $action->execute();
      $build = $event->getRenderArray();
    });

    \Drupal::service('theme.manager')->setActiveTheme(\Drupal::service('theme.initialization')->getActiveThemeByName('olivero'));
    $this->dispatchBasicRenderEvent([]);
    $this->assertEquals('claro', \Drupal::theme()->getActiveTheme()->getName());
  }

}
