<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Kernel tests regarding ECA render EntityForm action.
 *
 * @group eca
 * @group eca_render
 */
class EntityFormTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_entity_form".
   */
  public function testEntityForm(): void {
    /** @var \Drupal\eca_render\Plugin\Action\EntityForm $action */
    $action = $this->actionManager->createInstance('eca_render_entity_form', [
      'operation' => 'default',
      'object' => 'node',
      'name' => '',
      'token_name' => '',
      'weight' => '100',
      'mode' => 'append',
    ]);

    $build = [];
    $this->eventDispatcher->addListener(RenderBasicsEvents::BASIC, function (BasicRenderEvent $event) use (&$action, &$build) {
      $action->setEvent($event);
      $node = $this->tokenService->getTokenData('node');
      if ($action->access($node)) {
        $action->execute($node);
      }
      $build = $event->getRenderArray();
    });

    $this->tokenService->addTokenData('node', Node::create([
      'title' => $this->randomMachineName(),
      'body' => $this->randomMachineName(),
      'type' => 'article',
      'status' => TRUE,
    ]));
    $this->dispatchBasicRenderEvent([]);

    $this->assertFalse(isset($build[0]), "User has no access to edit the node.");

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $this->dispatchBasicRenderEvent([]);
    $this->assertTrue(isset($build[0]), "Admin has access to edit the node.");
  }

}
