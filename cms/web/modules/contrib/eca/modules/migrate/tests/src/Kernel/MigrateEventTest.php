<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\migrate\MigrateExecutable;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_migrate" event plugin.
 *
 * @group eca
 * @group eca_migrate
 */
class MigrateEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'migrate',
    'field',
    'user',
    'eca',
    'eca_migrate',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests list item count comparison.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testProperInstantiation(): void {
    /** @var \Drupal\eca\PluginManager\Event $eventManager */
    $eventManager = \Drupal::service('plugin.manager.eca.event');

    /** @var \Drupal\eca_migrate\Plugin\ECA\Event\MigrateEvent $event */
    $event = $eventManager->createInstance('migrate:map_save', []);
    $this->assertEquals('migrate', $event->getBaseId());
  }

  /**
   * Tests reacting upon events provided by "eca_migrate".
   */
  public function testMigrateEvents(): void {
    // This config does the following:
    // 1. It reacts upon all migrate events.
    // 2. It increments an array entry for each triggered event.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_migrate_events',
      'label' => 'ECA migrate events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'idmap_message' => [
          'plugin' => 'migrate:idmap_message',
          'label' => 'React upon idmap_message.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_idmap_message', 'condition' => ''],
          ],
        ],
        'map_delete' => [
          'plugin' => 'migrate:map_delete',
          'label' => 'React upon map_delete.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_map_delete', 'condition' => ''],
          ],
        ],
        'map_save' => [
          'plugin' => 'migrate:map_save',
          'label' => 'React upon map_save.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_map_save', 'condition' => ''],
          ],
        ],
        'post_import' => [
          'plugin' => 'migrate:post_import',
          'label' => 'React upon post_import.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_post_import', 'condition' => ''],
          ],
        ],
        'post_rollback' => [
          'plugin' => 'migrate:post_rollback',
          'label' => 'React upon post_rollback.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_post_rollback', 'condition' => ''],
          ],
        ],
        'post_row_delete' => [
          'plugin' => 'migrate:post_row_delete',
          'label' => 'React upon post_row_delete.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_post_row_delete', 'condition' => ''],
          ],
        ],
        'post_row_save' => [
          'plugin' => 'migrate:post_row_save',
          'label' => 'React upon post_row_save.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_post_row_save', 'condition' => ''],
          ],
        ],
        'pre_import' => [
          'plugin' => 'migrate:pre_import',
          'label' => 'React upon pre_import.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_pre_import', 'condition' => ''],
          ],
        ],
        'pre_rollback' => [
          'plugin' => 'migrate:pre_rollback',
          'label' => 'React upon pre_rollback.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_pre_rollback', 'condition' => ''],
          ],
        ],
        'pre_row_delete' => [
          'plugin' => 'migrate:pre_row_delete',
          'label' => 'React upon pre_row_delete.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_pre_row_delete', 'condition' => ''],
          ],
        ],
        'pre_row_save' => [
          'plugin' => 'migrate:pre_row_save',
          'label' => 'React upon pre_row_save.',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_pre_row_save', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'increment_idmap_message' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment idmap_message',
          'configuration' => [
            'key' => 'idmap_message_inc',
          ],
          'successors' => [],
        ],
        'increment_map_delete' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'map_delete_inc',
          ],
          'successors' => [],
        ],
        'increment_map_save' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'map_save_inc',
          ],
          'successors' => [],
        ],
        'increment_post_import' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'post_import_inc',
          ],
          'successors' => [],
        ],
        'increment_post_rollback' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'post_rollback_inc',
          ],
          'successors' => [],
        ],
        'increment_post_row_delete' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'row_delete_inc',
          ],
          'successors' => [],
        ],
        'increment_post_row_save' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'row_save_inc',
          ],
          'successors' => [],
        ],
        'increment_pre_import' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'pre_import_inc',
          ],
          'successors' => [],
        ],
        'increment_pre_rollback' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'pre_rollback_inc',
          ],
          'successors' => [],
        ],
        'increment_pre_row_delete' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'pre_row_delete_inc',
          ],
          'successors' => [],
        ],
        'increment_pre_row_save' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'pre_row_save_inc',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    // Create an invalid migration (user 2 name having invalid characters).
    $definition = [
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          [
            'id' => 2,
            'name' => $this->randomString(99),
            'mail' => 'hello@local.local',
          ],
          [
            'id' => 3,
            'name' => $this->randomMachineName(8),
            'mail' => 'hello2@local.local',
          ],
        ],
        'ids' => [
          'id' => ['type' => 'integer'],
        ],
      ],
      'process' => [
        'name' => 'name',
        'mail' => 'mail',
      ],
      'destination' => [
        'plugin' => 'entity:user',
        'validate' => TRUE,
      ],
    ];

    // Run the invalid migration, which should produce one message.
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    (new MigrateExecutable($migration))->import();

    $this->assertSame(1, ArrayIncrement::$array['idmap_message_inc']);
    $this->assertSame(2, ArrayIncrement::$array['map_save_inc']);

    // Now run a valid migration.
    $definition['source']['data_rows'][0]['name'] = $this->randomMachineName(8);
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $executable = new MigrateExecutable($migration);
    $executable->import();

    $this->assertSame(4, ArrayIncrement::$array['pre_row_save_inc'], "Two from invalid, plus two from valid definition.");
    $this->assertSame(2, ArrayIncrement::$array['row_save_inc']);
    $this->assertSame(2, ArrayIncrement::$array['pre_import_inc']);
    $this->assertSame(2, ArrayIncrement::$array['post_import_inc']);

    $executable->rollback();
    $this->assertSame(2, ArrayIncrement::$array['map_delete_inc']);
    $this->assertSame(1, ArrayIncrement::$array['pre_rollback_inc']);
    $this->assertSame(1, ArrayIncrement::$array['post_rollback_inc']);

    // Import again after rollback, and manually delete one entry.
    $executable->import();
    $migration->getIdMap()->delete(['id' => 2]);
    $this->assertSame(1, ArrayIncrement::$array['pre_row_delete_inc']);
    $this->assertSame(1, ArrayIncrement::$array['row_delete_inc']);
  }

}
