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
 * Kernel tests for the "eca_entity_exists" condition plugin.
 *
 * @group eca
 * @group eca_content
 */
class EntityExistsTest extends KernelTestBase {

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
   * Tests EntityExists.
   */
  public function testEntityExists() {
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

    /** @var \Drupal\eca\PluginManager\Condition $condition_manager */
    $condition_manager = \Drupal::service('plugin.manager.eca.condition');
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

    // Create a plugin for evaluating entity existence.
    $defaults = [
      'from' => 'current',
      'entity_type' => '_none',
      'entity_id' => '',
      'revision_id' => '',
      'properties' => '',
      'langcode' => '_interface',
      'latest_revision' => FALSE,
      'unchanged' => FALSE,
    ];
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'User without permissions must not have access.');

    // Now switch to privileged user.
    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'User with permissions must have access.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'revision_id' => $first_vid,
    ] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Node must be available.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'langcode' => 'en',
    ] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Node must be available.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'langcode' => 'de',
    ] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertFalse($condition->evaluate(), 'Node must not exist because the translation is not available.');

    $node->addTranslation('de', [
      'type' => 'article',
      'title' => 'ECA ist super!',
      'langcode' => 'de',
      'uid' => 1,
      'status' => 0,
    ])->save();
    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'langcode' => 'de',
    ] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Node must exist now because the translation is now available.');

    /** @var \Drupal\eca_content\Plugin\ECA\Condition\EntityExists $condition */
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'langcode' => 'en',
      'from' => 'id',
      'entity_type' => 'node',
      'entity_id' => '[mynode:nid]',
      'latest_revision' => TRUE,
    ] + $defaults);
    $token_services->addTokenData('mynode', $node);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Node must exist because the translation and latest revision is available.');

    $node->title = 'Changed on runtime';
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'unchanged' => TRUE,
    ] + $defaults);
    $condition->setContextValue('entity', $node);
    $this->assertTrue($condition->evaluate(), 'Node must exist because it is stored in the database.');

    // Load by properties.
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 456\nuid: 1",
    ] + $defaults);
    $this->assertTrue($condition->evaluate(), 'Node must exist.');
    $condition = $condition_manager->createInstance('eca_entity_exists', [
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 88888\nuid: 1",
    ] + $defaults);
    $this->assertFalse($condition->evaluate(), 'Node must not exist.');

    $account_switcher->switchBack();
  }

}
