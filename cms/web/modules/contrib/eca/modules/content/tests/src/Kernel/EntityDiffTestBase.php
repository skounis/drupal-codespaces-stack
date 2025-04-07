<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Base class for Kernel tests for the entity diff condition and action plugin.
 *
 * @group eca
 * @group eca_content
 */
abstract class EntityDiffTestBase extends KernelTestBase {

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

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $node_type->save();
    node_add_body_field($node_type);

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));
  }

  /**
   * Creates and gets a node.
   *
   * @param string $title
   *   The title.
   * @param string $body
   *   The body.
   *
   * @return \Drupal\node\Entity\Node
   *   The node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected static function createAndGetNode(string $title, string $body): Node {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'body' => $body,
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Puts the given node in the token stack.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   */
  protected static function addNodeAsToken(Node $node): void {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('compare', $node);
  }

  /**
   * Gets the given token value.
   *
   * @param string $value
   *   The token value.
   *
   * @return mixed
   *   The replaced token value.
   */
  protected static function getTokenValue(string $value): mixed {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    return $token_services->replaceClear($value);
  }

  /**
   * Gets the config.
   *
   * @param array $includeFields
   *   The include fields.
   * @param array $excludeFields
   *   The exclude fields.
   *
   * @return array
   *   The config.
   */
  protected static function getConfig(array $includeFields = [], array $excludeFields = []): array {
    return [
      'token_name' => 'result',
      'negate' => FALSE,
      'entity' => '',
      'compare_token_name' => '[compare]',
      'include_fields' => $includeFields,
      'exclude_fields' => $excludeFields,
    ];
  }

}
