<?php

namespace Drupal\Tests\eca_access\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca_access\Event\EntityAccess;
use Drupal\eca_access\Event\FieldAccess;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Execution chain tests using plugins of eca_access.
 *
 * @group eca
 * @group eca_access
 */
class AccessExecutionChainTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_access',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    Role::create(['id' => 'test_role_eca', 'label' => 'Test Role ECA'])->save();
    User::create([
      'uid' => 2,
      'name' => 'authenticated',
      'roles' => ['test_role_eca'],
    ])->save();
    user_role_grant_permissions('test_role_eca', [
      'access content',
    ]);

    // Create an Article content type.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    node_add_body_field($node_type);
    // Create a Page content type.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    node_add_body_field($node_type);
  }

  /**
   * Tests entity access using eca_access plugins.
   */
  public function testEntityAccess(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $article->save();
    $this->assertFalse($article->access('view'));

    // This config does the following:
    // 1. It reacts upon determining entity access, restricted to node article.
    // 2. Upon that, it grants access for anonymous users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'entity_access',
      'label' => 'ECA entity access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'entity_access' => [
          'plugin' => 'access:entity',
          'label' => 'Node article access',
          'configuration' => [
            'entity_type_id' => 'node',
            'bundle' => 'article',
            'operation' => 'view',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $event_token_data = [];
    $event_dispatcher->addListener(EcaEvents::BEFORE_INITIAL_EXECUTION, static function (BeforeInitialExecutionEvent $event) use (&$event_token_data) {
      if ($event->getEvent() instanceof EntityAccess) {
        $event_token_data['operation'] = \Drupal::token()->replace('[event:operation]');
        $event_token_data['uid'] = \Drupal::token()->replace('[event:uid]');
      }
    }, -1000);

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $article->save();
    $this->assertTrue($article->access('view'));
    $this->assertEquals('view', $event_token_data['operation']);
    $this->assertEquals('2', $event_token_data['uid']);

    $page = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $page->save();
    $this->assertFalse($page->access('view'), "Access must still be revoked on nodes other than articles.");
  }

  /**
   * Tests field access using eca_access plugins.
   */
  public function testFieldAccess(): void {
    user_role_grant_permissions('test_role_eca', [
      'administer nodes',
      'bypass node access',
    ]);
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
      'body' => ['value' => $this->randomMachineName()],
    ]);
    $article->save();
    $this->assertTrue($article->access('view'));
    $this->assertTrue($article->title->access('edit'));
    $this->assertTrue($article->body->access('edit'));

    // This config does the following:
    // 1. It reacts upon determining field access, restricted to node article
    //    and the body field.
    // 2. Upon that, it blocks access for all users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'entity_access',
      'label' => 'ECA entity access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'field_access' => [
          'plugin' => 'access:field',
          'label' => 'Node article body field access',
          'configuration' => [
            'entity_type_id' => 'node',
            'bundle' => 'article',
            'operation' => 'edit',
            'field_name' => 'body',
          ],
          'successors' => [
            ['id' => 'revoke_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'revoke_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Revoke access',
          'configuration' => [
            'access_result' => 'forbidden',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    $event_token_data = [];
    $event_dispatcher->addListener(EcaEvents::BEFORE_INITIAL_EXECUTION, static function (BeforeInitialExecutionEvent $event) use (&$event_token_data) {
      if ($event->getEvent() instanceof FieldAccess) {
        $event_token_data['operation'] = \Drupal::token()->replace('[event:operation]');
        $event_token_data['uid'] = \Drupal::token()->replace('[event:uid]');
        $event_token_data['field'] = \Drupal::token()->replace('[event:field]');
      }
    }, -1000);

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
      'body' => ['value' => $this->randomMachineName()],
    ]);
    $article->save();
    $this->assertTrue($article->access('view'));
    $this->assertTrue($article->title->access('edit'));
    $this->assertFalse(isset($event_token_data['field']), "There is no active field access event configured for the title field.");
    $this->assertFalse($article->body->access('edit'));
    $this->assertEquals('body', $event_token_data['field']);
    $this->assertEquals('edit', $event_token_data['operation']);
    $this->assertEquals('2', $event_token_data['uid']);

    $page = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
      'body' => ['value' => $this->randomMachineName()],
    ]);
    $page->save();
    $this->assertTrue($page->access('view'));
    $this->assertTrue($page->title->access('edit'));
    $this->assertTrue($page->body->access('edit'));
  }

  /**
   * Tests entity create access using eca_access plugins.
   */
  public function testCreateAccess(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));

    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');
    $this->assertFalse($access_handler->createAccess('article'));

    // This config does the following:
    // 1. It reacts upon determining create access, restricted to node article.
    // 2. Upon that, it grants access for all users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'entity_access',
      'label' => 'ECA entity create access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'create_access' => [
          'plugin' => 'access:create',
          'label' => 'Node article create access',
          'configuration' => [
            'entity_type_id' => 'node',
            'bundle' => 'article',
            'langcode' => '',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    $this->assertTrue($access_handler->createAccess('article'));
  }

  /**
   * Tests access checks using a different account than the current user.
   */
  public function testEntityAccessDifferentAccount(): void {
    User::create([
      'uid' => 3,
      'name' => 'authenticated2',
      'roles' => ['test_role_eca'],
    ])->save();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $article->save();
    $this->assertFalse($article->access('view'));

    // This config does the following:
    // 1. It reacts upon determining entity access, restricted to node article.
    // 2. Upon that, it grants access for user ID 3.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'entity_access_different_account',
      'label' => 'ECA entity access different account',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'entity_access' => [
          'plugin' => 'access:entity',
          'label' => 'Node article access',
          'configuration' => [
            'entity_type_id' => 'node',
            'bundle' => 'article',
            'operation' => 'view',
          ],
          'successors' => [
            ['id' => 'array_write', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [
        'array_has_key_value' => [
          'plugin' => 'eca_test_array_has_key_and_value',
          'configuration' => [
            'key' => 'account_id',
            'value' => '3',
          ],
        ],
      ],
      'gateways' => [],
      'actions' => [
        'array_write' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write account user into array',
          'configuration' => [
            'key' => 'account_id',
            'value' => '[account:uid]',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => 'array_has_key_value'],
          ],
        ],
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $article->save();
    $this->assertFalse($article->access('view'));
    $this->assertTrue($article->access('view', User::load(3)));

    $page = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => FALSE,
    ]);
    $page->save();
    $this->assertFalse($page->access('view'), "Access must still be revoked on nodes other than articles.");
    $this->assertFalse($page->access('view', User::load(3)), "Access must still be revoked on nodes other than articles.");
  }

}
