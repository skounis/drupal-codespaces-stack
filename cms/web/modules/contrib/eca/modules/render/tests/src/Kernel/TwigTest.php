<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\user\Entity\User;

/**
 * Kernel tests regarding ECA render Twig action.
 *
 * @group eca
 * @group eca_render
 */
class TwigTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_twig".
   */
  public function testTwig(): void {
    /** @var \Drupal\eca_render\Plugin\Action\Twig $action */
    $action = $this->actionManager->createInstance('eca_render_twig', [
      'template' => 'Hello {{ user.name.value }}!',
      'value' => '[user]',
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

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $this->dispatchBasicRenderEvent();

    $this->assertEquals('Hello admin!', $build[0]['#markup']);
  }

}
