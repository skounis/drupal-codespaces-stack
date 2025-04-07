<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;

/**
 * Kernel tests for reacting upon events provided by "eca_content".
 *
 * @group eca
 * @group eca_content
 */
class ContentEventsTest extends KernelTestBase {

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
    'language',
    'content_translation',
    'eca',
    'eca_content',
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
  }

  /**
   * Tests reacting upon content bundle events.
   */
  public function testBundleEvents(): void {
    // This config does the following:
    // 1. It reacts upon all available bundle events.
    // 2. Upon that, it writes expected token values into a static array.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'bundle_events',
      'label' => 'ECA content bundle events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'bundlecreate' => [
          'plugin' => 'content_entity:bundlecreate',
          'label' => 'bundlecreate',
          'configuration' => [
            'type' => 'node type2',
          ],
          'successors' => [
            ['id' => 'write_bundlecreate', 'condition' => ''],
          ],
        ],
        'bundledelete' => [
          'plugin' => 'content_entity:bundledelete',
          'label' => 'bundlecreate',
          'configuration' => [
            'type' => 'node type2',
          ],
          'successors' => [
            ['id' => 'write_bundledelete', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_bundlecreate' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write bundlecreate',
          'configuration' => [
            'key' => 'bundlecreate',
            'value' => 'bundlecreate [event:machine_name]',
          ],
          'successors' => [],
        ],
        'write_bundledelete' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write bundledelete',
          'configuration' => [
            'key' => 'bundledelete',
            'value' => 'bundledelete [event:machine_name]',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'type1',
      'name' => 'Type one',
      'new_revision' => TRUE,
    ]);
    $node_type->save();

    $this->assertTrue(!isset(ArrayWrite::$array['bundlecreate']), "The configuration only listens for type2, not type1.");
    $this->assertTrue(!isset(ArrayWrite::$array['bundledelete']), "The configuration only listens for type2, not type1.");

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'type2',
      'name' => 'Type two',
      'new_revision' => TRUE,
    ]);
    $node_type->save();

    $this->assertEquals('bundlecreate eca.content_entity.bundlecreate', ArrayWrite::$array['bundlecreate']);
    $this->assertTrue(!isset(ArrayWrite::$array['bundledelete']));
    $node_type->delete();
    $this->assertEquals('bundledelete eca.content_entity.bundledelete', ArrayWrite::$array['bundledelete']);
  }

  /**
   * Tests reacting upon content CRUD events.
   */
  public function testContentCrudEvents(): void {
    // This config does the following:
    // 1. It reacts upon all available content CRUD events.
    // 2. Upon that, it writes expected token values into a static array.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'content_crud_events',
      'label' => 'ECA content CRUD events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'create' => [
          'plugin' => 'content_entity:create',
          'label' => 'create',
          'configuration' => [
            'type' => 'node _all',
          ],
          'successors' => [
            ['id' => 'write_create', 'condition' => ''],
          ],
        ],
        'revisioncreate' => [
          'plugin' => 'content_entity:revisioncreate',
          'label' => 'revisioncreate',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_revisioncreate', 'condition' => ''],
          ],
        ],
        'preload' => [
          'plugin' => 'content_entity:preload',
          'label' => 'preload',
          'configuration' => [
            'type' => 'node _all',
          ],
          'successors' => [
            ['id' => 'write_preload', 'condition' => ''],
          ],
        ],
        'load' => [
          'plugin' => 'content_entity:load',
          'label' => 'load',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_load', 'condition' => ''],
          ],
        ],
        'storageload' => [
          'plugin' => 'content_entity:storageload',
          'label' => 'storageload',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_storageload', 'condition' => ''],
          ],
        ],
        'presave' => [
          'plugin' => 'content_entity:presave',
          'label' => 'presave',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_presave', 'condition' => ''],
          ],
        ],
        'insert' => [
          'plugin' => 'content_entity:insert',
          'label' => 'insert',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_insert', 'condition' => ''],
          ],
        ],
        'update' => [
          'plugin' => 'content_entity:update',
          'label' => 'update',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_update', 'condition' => ''],
          ],
        ],
        'translationcreate' => [
          'plugin' => 'content_entity:translationcreate',
          'label' => 'translationcreate',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_translationcreate', 'condition' => ''],
          ],
        ],
        'translationinsert' => [
          'plugin' => 'content_entity:translationinsert',
          'label' => 'translationinsert',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_translationinsert', 'condition' => ''],
          ],
        ],
        'translationdelete' => [
          'plugin' => 'content_entity:translationdelete',
          'label' => 'translationdelete',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_translationdelete', 'condition' => ''],
          ],
        ],
        'predelete' => [
          'plugin' => 'content_entity:predelete',
          'label' => 'predelete',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_predelete', 'condition' => ''],
          ],
        ],
        'delete' => [
          'plugin' => 'content_entity:delete',
          'label' => 'delete',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_delete', 'condition' => ''],
          ],
        ],
        'revisiondelete' => [
          'plugin' => 'content_entity:revisiondelete',
          'label' => 'revisiondelete',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_revisiondelete', 'condition' => ''],
          ],
        ],
        'view' => [
          'plugin' => 'content_entity:view',
          'label' => 'view',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_view', 'condition' => ''],
          ],
        ],
        'prepareview' => [
          'plugin' => 'content_entity:prepareview',
          'label' => 'prepareview',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_prepareview', 'condition' => ''],
          ],
        ],
        'validate' => [
          'plugin' => 'content_entity:validate',
          'label' => 'validate',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_validate', 'condition' => ''],
          ],
        ],
        'fieldvaluesinit' => [
          'plugin' => 'content_entity:fieldvaluesinit',
          'label' => 'fieldvaluesinit',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'write_fieldvaluesinit', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_create' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write create',
          'configuration' => [
            'key' => 'create',
            'value' => 'create [node:title]',
          ],
          'successors' => [],
        ],
        'write_revisioncreate' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write revisioncreate',
          'configuration' => [
            'key' => 'revisioncreate',
            'value' => 'revisioncreate [node:title]',
          ],
          'successors' => [],
        ],
        'write_preload' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write preload',
          'configuration' => [
            'key' => 'preload',
            'value' => 'preload [event:entity_type_id]',
          ],
          'successors' => [],
        ],
        'write_load' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write load',
          'configuration' => [
            'key' => 'load',
            'value' => 'load [node:title]',
          ],
          'successors' => [],
        ],
        'write_storageload' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write storageload',
          'configuration' => [
            'key' => 'storageload',
            'value' => 'storageload [node:title]',
          ],
          'successors' => [],
        ],
        'write_presave' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write presave',
          'configuration' => [
            'key' => 'presave',
            'value' => 'presave [node:title]',
          ],
          'successors' => [],
        ],
        'write_insert' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write insert',
          'configuration' => [
            'key' => 'insert',
            'value' => 'insert [node:title]',
          ],
          'successors' => [],
        ],
        'write_update' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write update',
          'configuration' => [
            'key' => 'update',
            'value' => 'update [node:title]',
          ],
          'successors' => [],
        ],
        'write_translationinsert' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write translationinsert',
          'configuration' => [
            'key' => 'translationinsert',
            'value' => 'translationinsert [node:title]',
          ],
          'successors' => [],
        ],
        'write_translationcreate' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write translationcreate',
          'configuration' => [
            'key' => 'translationcreate',
            'value' => 'translationcreate [node:title]',
          ],
          'successors' => [],
        ],
        'write_translationdelete' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write translationdelete',
          'configuration' => [
            'key' => 'translationdelete',
            'value' => 'translationdelete [node:title]',
          ],
          'successors' => [],
        ],
        'write_predelete' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write predelete',
          'configuration' => [
            'key' => 'predelete',
            'value' => 'predelete [node:title]',
          ],
          'successors' => [],
        ],
        'write_delete' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write delete',
          'configuration' => [
            'key' => 'delete',
            'value' => 'delete [node:title]',
          ],
          'successors' => [],
        ],
        'write_revisiondelete' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write revisiondelete',
          'configuration' => [
            'key' => 'revisiondelete',
            'value' => 'revisiondelete [node:title]',
          ],
          'successors' => [],
        ],
        'write_view' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write view',
          'configuration' => [
            'key' => 'view',
            'value' => 'view [node:title]',
          ],
          'successors' => [],
        ],
        'write_prepareview' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write prepareview',
          'configuration' => [
            'key' => 'prepareview',
            'value' => 'prepareview [node:title]',
          ],
          'successors' => [],
        ],
        'write_validate' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write validate',
          'configuration' => [
            'key' => 'validate',
            'value' => 'validate [node:title]',
          ],
          'successors' => [],
        ],
        'write_fieldvaluesinit' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write fieldvaluesinit',
          'configuration' => [
            'key' => 'fieldvaluesinit',
            'value' => 'fieldvaluesinit [node:title]',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $node = Node::create([
      'title' => 'English node',
      'langcode' => 'en',
      'status' => TRUE,
      'uid' => 0,
      'type' => 'article',
    ]);
    $this->assertEquals('create English node', ArrayWrite::$array['create']);
    $this->assertEquals('fieldvaluesinit English node', ArrayWrite::$array['fieldvaluesinit']);
    $node->validate();
    $this->assertEquals('validate English node', ArrayWrite::$array['validate']);
    $node->save();
    $this->assertEquals('presave English node', ArrayWrite::$array['presave']);
    $this->assertEquals('insert English node', ArrayWrite::$array['insert']);

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node->setNewRevision(TRUE);
    $revision = $node_storage->createRevision($node);
    $revision->title->value = 'English node advanced';
    $revision->save();
    $vid = $revision->getRevisionId();

    $this->assertEquals('presave English node advanced', ArrayWrite::$array['presave']);
    $this->assertEquals('update English node advanced', ArrayWrite::$array['update']);
    $this->assertEquals('revisioncreate English node', ArrayWrite::$array['revisioncreate'], "Revision creation happens before new values get written into the new revision.");

    $node_storage->load($node->id());
    $this->assertEquals('storageload English node advanced', ArrayWrite::$array['storageload']);
    $this->assertEquals('preload node', ArrayWrite::$array['preload']);
    $this->assertEquals('load English node advanced', ArrayWrite::$array['load']);

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($node);
    \Drupal::service('renderer')->renderRoot($build);
    $this->assertEquals('prepareview English node', ArrayWrite::$array['prepareview']);
    $this->assertEquals('view English node', ArrayWrite::$array['view']);

    $translation = $node->addTranslation('de', [
      'title' => 'Deutsche node',
      'langcode' => 'en',
      'status' => TRUE,
      'uid' => 0,
      'type' => 'article',
    ]);
    $this->assertEquals('translationcreate Deutsche node', ArrayWrite::$array['translationcreate']);
    $translation->save();
    $this->assertEquals('presave Deutsche node', ArrayWrite::$array['presave']);
    $this->assertEquals('update Deutsche node', ArrayWrite::$array['update']);
    $this->assertEquals('translationinsert Deutsche node', ArrayWrite::$array['translationinsert']);

    $node->removeTranslation('de');
    $node->save();
    $this->assertEquals('translationdelete Deutsche node', ArrayWrite::$array['translationdelete']);

    $node_storage->deleteRevision($vid);
    $this->assertEquals('revisiondelete English node advanced', ArrayWrite::$array['revisiondelete']);

    $node = $node_storage->loadUnchanged($node->id());
    $node->delete();
    $this->assertEquals('predelete English node', ArrayWrite::$array['predelete']);
    $this->assertEquals('delete English node', ArrayWrite::$array['delete']);
  }

}
