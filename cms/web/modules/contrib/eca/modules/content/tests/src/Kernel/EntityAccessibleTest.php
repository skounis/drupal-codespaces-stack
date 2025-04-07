<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the access condition plugins.
 *
 * Access condition plugins:
 * - eca_entity_is_accessible
 * - eca_entity_field_is_accessible.
 *
 * @group eca
 * @group eca_content
 */
class EntityAccessibleTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_content',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    Role::create(['id' => 'test_role_eca', 'label' => 'Test Role ECA'])->save();
    user_role_grant_permissions('test_role_eca', ['access content']);
    User::create([
      'uid' => 2,
      'name' => 'authenticated',
      'roles' => ['test_role_eca'],
    ])->save();
    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $node_type->save();
    node_add_body_field($node_type);
  }

  /**
   * Tests EntityIsAccessible.
   */
  public function testEntityIsAccessible() {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    // Create a node that is not published.
    $node = Node::create([
      'type' => 'article',
      'title' => '123',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $node->save();

    // Create a plugin for evaluating entity is accessible.
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $this->assertFalse($condition->evaluate(), 'No access without an entity context.');

    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'Create access on a non-new node is not possible.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'update']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    // Switch to authenticated user without any permissions.
    $account_switcher->switchTo(User::load(2));

    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $this->assertFalse($condition->evaluate(), 'No access without an entity context.');

    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'Create access on a non-new node is not possible.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'update']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    // Now publish the node. Runtime cache needs to be cleared to take effect.
    $node->setPublished()->save();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $node = Node::load($node->id());

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'User is authenticated and thus must have access to the content.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'update']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    // Now grant permission to update the node.
    user_role_grant_permissions('test_role_eca', ['edit any article content']);

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'User is authenticated and thus must have access to the content.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'update']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User has permission to update the node.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access to delete the node.');

    // End of tests with authenticated user.
    $account_switcher->switchBack();

    // Now switch to Privileged user.
    $account_switcher->switchTo(User::load(1));

    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $this->assertFalse($condition->evaluate(), 'No access without an entity context.');

    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'Create access on a non-new node is not possible.');

    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'create']);
    $condition->setContextValue('entity', Node::create(['type' => 'article']));
    $this->assertTrue($condition->evaluate(), 'Create access on a new node must be possible for Privileged user.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Privileged user must have view access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'update']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Privileged user must have update access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_is_accessible', ['operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Privileged user must have delete access.');

    $account_switcher->switchBack();
  }

  /**
   * Tests EntityFieldIsAccessible.
   */
  public function testEntityFieldIsAccessible() {
    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    // Create a node that is not published.
    $node = Node::create([
      'type' => 'article',
      'title' => '123',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $node->save();

    // Switch to authenticated user without any permissions.
    $account_switcher->switchTo(User::load(2));

    // Create a plugin for evaluating entity field is accessible.
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'field_i_do_not_exist', 'operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'Non-existent field must always evaluate to false.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'edit']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    // Now publish the node. Runtime cache needs to be cleared to take effect.
    $node->setPublished()->save();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $node = Node::load($node->id());

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'User is authenticated and thus must have access to the content.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'edit']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    // Now grant permission to update the node.
    user_role_grant_permissions('test_role_eca', ['edit any article content']);

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'User is authenticated and thus must have access to the content.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'edit']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User has permission to update the node.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access to delete the node.');

    // End of tests with authenticated user.
    $account_switcher->switchBack();

    // Now switch to Privileged user.
    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'field_i_do_not_exist', 'operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'Non-existent field must always evaluate to false.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'view']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Privileged user must have view access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'edit']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Privileged user must have update access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityFieldIsAccessible $condition */
    $condition = $condition_manager->createInstance('eca_entity_field_is_accessible',
      ['field_name' => 'body', 'operation' => 'delete']);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Privileged user must have delete access.');

    $account_switcher->switchBack();
  }

}
