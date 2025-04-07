<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Base class for plugin tests of the "eca_config" module.
 */
abstract class Base extends KernelTestBase {

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
    'eca_config',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    Role::create(['id' => 'test_role_eca', 'label' => 'Test Role ECA'])->save();
    User::create([
      'uid' => 2,
      'name' => 'authenticated',
      'roles' => ['test_role_eca'],
    ])->save();
    user_role_grant_permissions('test_role_eca', [
      'administer site configuration',
    ]);
    // Create an article as node type.
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
  }

}
