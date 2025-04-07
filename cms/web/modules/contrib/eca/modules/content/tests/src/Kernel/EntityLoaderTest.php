<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;

/**
 * Kernel tests for the "eca_content.service.entity_loader" service.
 *
 * @group eca
 * @group eca_content
 */
class EntityLoaderTest extends KernelTestBase {

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
   * Tests EntityLoader.
   */
  public function testEntityLoader() {
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

    /** @var \Drupal\eca_content\Service\EntityLoader $entity_loader */
    $entity_loader = \Drupal::service('eca_content.service.entity_loader');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'title' => '123',
      'langcode' => 'en',
      'uid' => 0,
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
    $this->assertTrue($entity_loader->loadEntity($node, $defaults) instanceof NodeInterface, 'Entity must exist.');
    $this->assertEquals($node->id(), $entity_loader->loadEntity($node, $defaults)->id(), 'Node ID must match up.');

    $this->assertTrue($entity_loader->loadEntity($node, ['revision_id' => $first_vid] + $defaults) instanceof NodeInterface, 'Entity must exist.');
    $this->assertEquals($node->id(), $entity_loader->loadEntity($node, ['revision_id' => $first_vid] + $defaults)->id(), 'Node ID must match up.');
    $this->assertEquals($first_vid, $entity_loader->loadEntity($node, ['revision_id' => $first_vid] + $defaults)->getRevisionId(), 'Revision ID must match up (first revision ID).');

    $this->assertTrue($entity_loader->loadEntity($node, ['langcode' => 'en'] + $defaults) instanceof NodeInterface, 'Entity must exist.');
    $this->assertEquals($node->id(), $entity_loader->loadEntity($node, ['langcode' => 'en'] + $defaults)->id(), 'Node ID must match up.');
    $this->assertEquals('en', $entity_loader->loadEntity($node, ['langcode' => 'en'] + $defaults)->language()->getId(), 'Language ID must match up (en).');

    $this->assertNull($entity_loader->loadEntity($node, ['langcode' => 'de'] + $defaults), 'Entity must not exist as the translation (de) is not available.');

    $node->addTranslation('de', [
      'type' => 'article',
      'title' => 'ECA ist super!',
      'langcode' => 'de',
      'uid' => 1,
      'status' => 0,
    ])->save();
    $this->assertTrue($entity_loader->loadEntity($node, ['langcode' => 'de'] + $defaults) instanceof NodeInterface, 'Entity must exist now as its translation (de) is now available.');
    $this->assertEquals($node->id(), $entity_loader->loadEntity($node, ['langcode' => 'de'] + $defaults)->id(), 'Node ID must match up.');
    $this->assertEquals('de', $entity_loader->loadEntity($node, ['langcode' => 'de'] + $defaults)->language()->getId(), 'Language ID must match up (de).');

    $token_services->addTokenData('mynode', $node);

    $entity = $entity_loader->loadEntity(NULL, [
      'langcode' => 'en',
      'from' => 'id',
      'entity_type' => 'node',
      'entity_id' => '[mynode:nid]',
      'latest_revision' => TRUE,
    ] + $defaults);
    $this->assertTrue($entity instanceof NodeInterface, 'Entity must exist.');
    $this->assertEquals($node->id(), $entity->id(), 'Node ID must match up.');

    $node->title = 'Changed on runtime';
    $entity = $entity_loader->loadEntity($node, ['unchanged' => TRUE] + $defaults);
    $this->assertTrue($entity instanceof NodeInterface, 'Entity must exist as it is stored in the database.');
    $this->assertEquals($node->id(), $entity->id(), 'Node ID must match up.');
    $this->assertEquals('456', $entity->label(), 'Node title must be the unchanged one.');

    // Load by properties.
    $entity = $entity_loader->loadEntity(NULL, [
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 456\nuid: 0",
    ] + $defaults);
    $this->assertTrue($entity instanceof NodeInterface, 'Entity must exist.');
    $entity = $entity_loader->loadEntity(NULL, [
      'from' => 'properties',
      'entity_type' => 'node',
      'properties' => "title: 88888\nuid: 1",
    ] + $defaults);
    $this->assertFalse($entity instanceof NodeInterface, 'Node must not exist.');
  }

}
