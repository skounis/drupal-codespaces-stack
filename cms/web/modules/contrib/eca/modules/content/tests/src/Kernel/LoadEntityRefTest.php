<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;

/**
 * Kernel tests for the "eca_token_load_entity_ref_ref" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class LoadEntityRefTest extends KernelTestBase {

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
    'token',
    'eca',
    'eca_content',
    'language',
    'content_translation',
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

    ConfigurableLanguage::create(['id' => 'de'])->save();
    // Set up language negotiation.
    $config = $this->config('language.types');
    $config->set('configurable', [
      LanguageInterface::TYPE_INTERFACE,
      LanguageInterface::TYPE_CONTENT,
    ]);
    $config->set('negotiation', [
      LanguageInterface::TYPE_INTERFACE => [
        'enabled' => [LanguageNegotiationUser::METHOD_ID => 0],
      ],
      LanguageInterface::TYPE_CONTENT => [
        'enabled' => [LanguageNegotiationUrl::METHOD_ID => 0],
      ],
    ]);
    $config->save();
    $config = $this->config('language.negotiation');
  }

  /**
   * Tests LoadEntityRef.
   */
  public function testLoadEntityRef() {
    // Create the Article content type with revisioning and translation enabled.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    ContentLanguageSettings::create([
      'id' => 'node.article',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'article',
      'default_langcode' => LanguageInterface::LANGCODE_DEFAULT,
      'language_alterable' => TRUE,
    ])->save();
    // Create a reference field.
    $field_definition = FieldStorageConfig::create([
      'field_name' => 'field_node_ref',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_definition->save();
    $field = FieldConfig::create([
      'field_storage' => $field_definition,
      'label' => 'A node reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $field->save();

    // Create a plaintext field to be used as token.
    FieldStorageConfig::create([
      'field_name' => 'field_node_ref_mn',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_node_ref_mn',
      'label' => 'The reference field machine name.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    // Create a reference field target for token.
    FieldStorageConfig::create([
      'field_name' => 'field_node_ref_target_token',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_node_ref_target_token',
      'label' => 'A node reference target token.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $referenced = Node::create([
      'type' => 'article',
      'title' => 'I am a referenced node.',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $referenced->save();

    $referenced_by_token = Node::create([
      'type' => 'article',
      'title' => 'I am a referenced node using tokens.',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $referenced_by_token->save();

    $node = Node::create([
      'type' => 'article',
      'title' => '123',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $node->save();
    $first_vid = $node->getRevisionId();
    $node->title = '456';
    $node->field_node_ref->target_id = $referenced->id();
    $node->field_node_ref_target_token->target_id = $referenced_by_token->id();
    $node->field_node_ref_mn->value = 'field_node_ref_target_token';
    $node->setNewRevision(TRUE);
    $node->save();

    // Create an action that that loads the referenced entity.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $defaults = [
      'token_name' => 'mynode',
      'from' => 'current',
      'entity_type' => '_none',
      'entity_id' => '',
      'revision_id' => '',
      'properties' => '',
      'langcode' => '_interface',
      'latest_revision' => FALSE,
      'unchanged' => FALSE,
      'field_name_entity_ref' => 'field_node_ref',
    ];
    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [] + $defaults);
    $this->assertFalse($action->access($node), 'User without permissions must not have access.');

    // Now switch to privileged user.
    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [] + $defaults);
    $this->assertTrue($action->access($node), 'User with permissions must have access.');
    $this->assertFalse($token_services->hasTokenData('mynode'), 'Token must not yet be defined.');
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must be defined.');
    $this->assertSame($referenced->id(), $token_services->getTokenData('mynode')->id());

    $token_services->addTokenData('node', $node);
    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'field_name_entity_ref' => '[node:field_node_ref_mn]',
    ] + $defaults);
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must be defined.');
    $this->assertSame($referenced_by_token->id(), $token_services->getTokenData('mynode')->id());

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'revision_id' => $first_vid,
    ] + $defaults);
    $action->execute($node);
    $this->assertFalse($token_services->hasTokenData('mynode'), 'Token must not be defined, because the reference does not exist in the first revision.');

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'langcode' => 'en',
    ] + $defaults);
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must be defined.');
    $this->assertSame($referenced->id(), $token_services->getTokenData('mynode')->id());

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'langcode' => 'de',
    ] + $defaults);
    $action->execute($node);
    $this->assertFalse($token_services->hasTokenData('mynode'), 'Token must not be defined anymore because the translation does not exist.');

    $node->addTranslation('de', [
      'type' => 'article',
      'title' => 'ECA ist super!',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ])->save();
    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'langcode' => 'de',
    ] + $defaults);
    $action->execute($node);
    $this->assertFalse($token_services->hasTokenData('mynode'), 'Token must not be defined because the translation has no reference.');

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $token_services->addTokenData('mynode', $node);
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'token_name' => 'english',
      'langcode' => 'en',
      'from' => 'id',
      'entity_type' => 'node',
      'entity_id' => '[mynode:nid]',
      'latest_revision' => TRUE,
    ] + $defaults);
    $this->assertFalse($token_services->hasTokenData('english'), 'Token must not be defined yet.');
    $action->execute(NULL);
    $this->assertTrue($token_services->hasTokenData('english'), 'Token must now be defined.');
    $this->assertEquals('en', $token_services->getTokenData('english')->language()->getId(), 'Langcode of referenced node must be english.');
    $this->assertEquals('I am a referenced node.', (string) $token_services->replace('[english:title]'), 'Title must match with the title of the referenced node.');

    $referenced = $token_services->getTokenData('english');
    $referenced->title = 'Changed on runtime';
    $this->assertEquals('Changed on runtime', $token_services->replace('[english:title]'), 'Runtime change must also affect Token replacement.');
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'token_name' => 'english',
      'unchanged' => TRUE,
    ] + $defaults);
    $action->execute($node);
    $this->assertEquals('Changed on runtime', $token_services->replace('[english:title]'), 'Runtime change must still be the changed once, because the option to load unchanged values belongs to the node that holds the reference, not the reference itself.');
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'token_name' => 'english',
      'langcode' => 'en',
      'from' => 'id',
      'entity_type' => 'node',
      'entity_id' => '[english:nid]',
      'latest_revision' => TRUE,
      'unchanged' => TRUE,
    ] + $defaults);
    $action->execute(NULL);
    $this->assertFalse($token_services->hasTokenData('english'), 'The token must not be defined anymore, because the reference itself does not have a reference.');

    // Load by properties.
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'token_name' => 'another',
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 456\nuid: 1",
    ] + $defaults);
    $this->assertEquals('[another:title]', $token_services->replace('[another:title]'), 'Another node must not yet have been loaded.');
    $action->execute(NULL);
    $this->assertEquals('I am a referenced node.', $token_services->replace('[another:title]'), 'Loaded another node must be the one of the referenced node.');
    // Load a node by properties that does not exist.
    $action = $action_manager->createInstance('eca_token_load_entity_ref', [
      'token_name' => 'another',
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 456\nuid: 2",
    ] + $defaults);
    $action->execute($node);
    $this->assertEquals('[another:title]', $token_services->replace('[another:title]'), 'Another node must not be available anymore, because there is no such node with specified properties.');
    $this->assertFalse($token_services->hasTokenData('another'), 'The token service must not have "another" node token anymore.');

    $account_switcher->switchBack();
  }

}
