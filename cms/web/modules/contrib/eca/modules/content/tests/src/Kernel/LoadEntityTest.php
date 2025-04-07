<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;

/**
 * Kernel tests for the "eca_token_load_entity" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class LoadEntityTest extends KernelTestBase {

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
   * Tests LoadEntity.
   */
  public function testLoadEntity(): void {
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

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\node\NodeInterface $node */
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
    $node->setNewRevision(TRUE);
    $node->save();
    $second_vid = $node->getRevisionId();

    // Create an action that that loads the entity.
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
    ];
    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [] + $defaults);
    $this->assertFalse($action->access($node), 'User without permissions must not have access.');

    // Now switch to privileged user.
    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [] + $defaults);
    $this->assertTrue($action->access($node), 'User with permissions must have access.');
    $this->assertFalse($token_services->hasTokenData('mynode'), 'Token must not yet be defined.');
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must be defined.');
    $this->assertSame($node, $token_services->getTokenData('mynode'));

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'revision_id' => $first_vid,
    ] + $defaults);
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must be defined.');
    $this->assertSame($first_vid, $token_services->getTokenData('mynode')->getRevisionId(), 'Loaded node must be the first revision.');
    $this->assertNotSame($second_vid, $token_services->getTokenData('mynode')->getRevisionId(), 'Loaded node must not match up with the second revision.');

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'langcode' => 'en',
    ] + $defaults);
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must be defined.');
    $this->assertNotSame($first_vid, $token_services->getTokenData('mynode')->getRevisionId(), 'Loaded node must be the first revision.');
    $this->assertSame($second_vid, $token_services->getTokenData('mynode')->getRevisionId(), 'Loaded node must not match up with the second revision.');

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'langcode' => 'de',
    ] + $defaults);
    $action->execute($node);
    $this->assertFalse($token_services->hasTokenData('mynode'), 'Token must not be defined anymore because the translation does not exist.');

    $node->addTranslation('de', [
      'type' => 'article',
      'title' => 'ECA ist super!',
      'langcode' => 'de',
      'uid' => 1,
      'status' => 0,
    ])->save();
    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'langcode' => 'de',
    ] + $defaults);
    $action->execute($node);
    $this->assertTrue($token_services->hasTokenData('mynode'), 'Token must now be defined again.');
    $this->assertEquals('de', $token_services->getTokenData('mynode')->language()->getId());
    $this->assertEquals('ECA ist super!', (string) $token_services->replace('[mynode:title]'));

    /** @var \Drupal\eca_content\Plugin\Action\LoadEntity $action */
    $action = $action_manager->createInstance('eca_token_load_entity', [
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
    $this->assertEquals('en', $token_services->getTokenData('english')->language()->getId(), 'Langcode of node must be english.');
    $this->assertEquals($second_vid, $token_services->getTokenData('english')->getRevisionId(), 'Latest revision of english node must be the second one.');
    $this->assertEquals('456', (string) $token_services->replace('[english:title]'), 'Title must match with the title of the second english revision.');

    $node = $token_services->getTokenData('english');
    $node->title = 'Changed on runtime';
    $this->assertEquals('Changed on runtime', $token_services->replace('[english:title]'), 'Runtime change must also affect Token replacement.');
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'token_name' => 'english',
      'unchanged' => TRUE,
    ] + $defaults);
    $action->execute($node);
    $this->assertEquals('456', $token_services->replace('[english:title]'), 'Title must be the unchanged value from database.');
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'token_name' => 'english',
      'langcode' => 'en',
      'from' => 'id',
      'entity_type' => 'node',
      'entity_id' => '[mynode:nid]',
      'latest_revision' => TRUE,
      'unchanged' => TRUE,
    ] + $defaults);
    $action->execute(NULL);
    $this->assertEquals('456', $token_services->replace('[english:title]'), 'Title must be the unchanged value from database.');

    // Load by properties.
    $action = $action_manager->createInstance('eca_token_load_entity', [
      'token_name' => 'another',
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 456\nuid: 1",
    ] + $defaults);
    $this->assertEquals('[another:title]', $token_services->replace('[another:title]'), 'Another node must not yet have been loaded.');
    $action->execute(NULL);
    $this->assertEquals('456', $token_services->replace('[another:title]'), 'Loaded another node must be the one matching title "456".');
    // Load a node by properties that does not exist.
    $action = $action_manager->createInstance('eca_token_load_entity', [
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
