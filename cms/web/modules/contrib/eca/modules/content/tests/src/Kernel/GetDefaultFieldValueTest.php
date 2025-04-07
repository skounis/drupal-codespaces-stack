<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_get_default_field_value" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class GetDefaultFieldValueTest extends KernelTestBase {

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
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);
    // Create a multi-value text field.
    FieldStorageConfig::create([
      'field_name' => 'field_string_multi',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_string_multi',
      'label' => 'A string field having multiple values.',
      'entity_type' => 'node',
      'bundle' => 'article',
      'default_value' => [
        ['value' => 'Default One'],
        ['value' => 'Default Two'],
      ],
    ])->save();

    // Create a single-value entity reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_content',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'module' => 'core',
      'cardinality' => 1,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'label' => 'A single-value node reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_storage' => $field_storage,
      'default_value' => [
        ['target_uuid' => '1174e751-3862-4322-bf87-078e9421a763'],
      ],
    ])->save();

    $node = Node::create([
      'uuid' => '1174e751-3862-4322-bf87-078e9421a763',
      'type' => 'article',
      'uid' => 1,
      'title' => 'The referenced one',
    ]);
    $node->save();
  }

  /**
   * Tests GetDefaultFieldValue on a node.
   */
  public function testGetFieldValueNode(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $string = $this->randomMachineName(32);
    $text = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => '123',
      'body' => ['summary' => $summary, 'value' => $text],
      'field_string_multi' => [$string, $string . '2', $string . '3'],
    ]);
    $node->save();

    /** @var \Drupal\eca_content\Plugin\Action\GetDefaultFieldValue $action */
    $action = $action_manager->createInstance('eca_get_default_field_value', [
      'token_name' => 'my_custom_token:bodyvalue',
      'field_name' => 'body.value',
    ]);
    $this->assertFalse($action->access($node), 'User without permissions must not have access.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_content\Plugin\Action\GetDefaultFieldValue $action */
    $action = $action_manager->createInstance('eca_get_default_field_value', [
      'token_name' => 'my_custom_token:bodyvalue',
      'field_name' => 'body',
    ]);
    $this->assertTrue($action->access($node), 'User with permissions must have access.');
    $action->execute($node);
    $this->assertEquals('', $token_services->replaceClear('[my_custom_token:bodyvalue]'));

    $action = $action_manager->createInstance('eca_get_default_field_value', [
      'token_name' => 'string_multi_default',
      'field_name' => 'field_string_multi',
    ]);
    $this->assertTrue($action->access($node));
    $action->execute($node);
    $this->assertInstanceOf(DataTransferObject::class, $token_services->getTokenData('string_multi_default'));
    $this->assertCount(2, $token_services->getTokenData('string_multi_default')->toArray());
    $this->assertEquals('Default One', $token_services->getTokenData('string_multi_default')->toArray()[0]);
    $this->assertEquals('Default Two', $token_services->getTokenData('string_multi_default')->toArray()[1]);

    $action = $action_manager->createInstance('eca_get_default_field_value', [
      'token_name' => 'field_content_default',
      'field_name' => 'field_content',
    ]);
    $this->assertTrue($action->access($node));
    $action->execute($node);
    $this->assertInstanceOf(NodeInterface::class, $token_services->getTokenData('field_content_default'));
    $this->assertEquals('1174e751-3862-4322-bf87-078e9421a763', $token_services->getTokenData('field_content_default')->uuid());
    $this->assertEquals('The referenced one', $token_services->getTokenData('field_content_default')->label());

    $account_switcher->switchBack();
  }

}
