<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Kernel tests regarding ECA render EntityViewField action.
 *
 * @group eca
 * @group eca_render
 */
class EntityViewFieldTest extends RenderActionsTestBase {

  /**
   * Tests the action plugin "eca_render_entity_view_field".
   */
  public function testEntityViewField(): void {
    /** @var \Drupal\eca_render\Plugin\Action\EntityViewField $action */
    $action = $this->actionManager->createInstance('eca_render_entity_view_field', [
      'field_name' => 'body',
      'view_mode' => 'default',
      'display_options' => '',
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

    $body_value = $this->randomMachineName();
    $this->tokenService->addTokenData('node', Node::create([
      'title' => $this->randomMachineName(),
      'body' => $body_value,
      'type' => 'article',
      'status' => TRUE,
    ]));
    $this->dispatchBasicRenderEvent([]);

    $this->assertFalse(isset($build[0]), "User has no access to view the field.");

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $this->dispatchBasicRenderEvent([]);
    $this->assertTrue(isset($build[0]), "Admin has access to view the field.");
    $this->assertSame($body_value, $build[0][0]['#text']);
  }

}
