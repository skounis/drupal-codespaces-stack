<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\Importer\MissingContentEvent;
use Drupal\Core\Config\StorageComparer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\user\Entity\User;

/**
 * Kernel tests for events provided by "eca_base".
 *
 * @group eca
 * @group eca_base
 */
class ConfigEventsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'config_translation',
    'language',
    'eca',
    'eca_config',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests reacting upon config CRUD events.
   */
  public function testConfigCrudEvents(): void {
    // This config does the following:
    // 1. It reacts upon all config CRUD events.
    // 2. It writes the config name and site name into a static array.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_config_crud_events',
      'label' => 'ECA config CRUD events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'config_save' => [
          'plugin' => 'config:save',
          'label' => 'Configuration save',
          'configuration' => [
            'config_name' => '',
            'sync_mode' => '',
            'write_mode' => '',
          ],
          'successors' => [
            ['id' => 'array_write_config_name', 'condition' => ''],
            ['id' => 'array_write_site_name', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'array_write_config_name' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write config name into array',
          'configuration' => [
            'key' => 'config_name',
            'value' => '[config_name]',
          ],
          'successors' => [],
        ],
        'array_write_site_name' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write site name into array',
          'configuration' => [
            'key' => 'site_name',
            'value' => '[config:name]',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $system_settings = \Drupal::configFactory()->getEditable('system.site');
    $system_settings->set('name', 'My ECA site');
    $system_settings->save();

    $this->assertEquals('system.site', ArrayWrite::$array['config_name']);
    $this->assertEquals('My ECA site', ArrayWrite::$array['site_name']);
  }

  /**
   * Tests reacting upon the "config:collection_info" event.
   */
  public function testConfigCollectionEvent(): void {
    // This config does the following:
    // 1. It reacts upon the config collection event.
    // 2. Upon that, it increments a number in a static array.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_config_collection',
      'label' => 'ECA config collection',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'config_collection' => [
          'plugin' => 'config:collection_info',
          'label' => 'Configuration collection',
          'configuration' => [],
          'successors' => [
            ['id' => 'array_increment', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'array_increment' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment',
          'configuration' => [
            'key' => 'inc',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    ArrayIncrement::$array['inc'] = 0;

    /** @var \Drupal\Core\Config\ConfigManager $config_manager */
    $config_manager = \Drupal::service('config.manager');
    // Set the collection info to NULL so that the event is being triggered
    // again by ::getConfigCollectionInfo().
    $closure = (function () {
      $this->configCollectionInfo = NULL;
    })(...);
    $closure->call($config_manager);
    $config_manager->getConfigCollectionInfo();

    $this->assertSame(1, ArrayIncrement::$array['inc']);
  }

  /**
   * Tests reacting upon config import events.
   */
  public function testConfigImportEvents(): void {
    // This config does the following:
    // 1. It reacts upon all config import events.
    // 2. Upon that, it increments according numbers in a static array.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_config_import',
      'label' => 'ECA config import',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'config_import' => [
          'plugin' => 'config:import',
          'label' => 'Configuration import',
          'configuration' => [],
          'successors' => [
            ['id' => 'array_increment_import', 'condition' => ''],
          ],
        ],
        'config_import_missing_content' => [
          'plugin' => 'config:import_missing_content',
          'label' => 'Configuration import missing content',
          'configuration' => [],
          'successors' => [
            [
              'id' => 'array_increment_import_missing_content',
              'condition' => '',
            ],
          ],
        ],
        'config_import_validate' => [
          'plugin' => 'config:import_validate',
          'label' => 'Configuration import validate',
          'configuration' => [],
          'successors' => [
            ['id' => 'array_increment_import_validate', 'condition' => ''],
          ],
        ],
        'config_rename' => [
          'plugin' => 'config:rename',
          'label' => 'Configuration rename',
          'configuration' => [],
          'successors' => [
            ['id' => 'array_increment_rename', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'array_increment_import' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment import',
          'configuration' => [
            'key' => 'import_inc',
          ],
          'successors' => [],
        ],
        'array_increment_import_missing_content' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment import missing content',
          'configuration' => [
            'key' => 'import_missing_content_inc',
          ],
          'successors' => [],
        ],
        'array_increment_import_validate' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment import validate',
          'configuration' => [
            'key' => 'import_validate_inc',
          ],
          'successors' => [],
        ],
        'array_increment_rename' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment rename',
          'configuration' => [
            'key' => 'import_rename_inc',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    // Set up the ConfigImporter object for testing.
    $storage_comparer = new StorageComparer(
      $this->container->get('config.storage.sync'),
      $this->container->get('config.storage')
    );
    $config_importer = new ConfigImporter(
      $storage_comparer->createChangelist(),
      $this->container->get('event_dispatcher'),
      $this->container->get('config.manager'),
      $this->container->get('lock'),
      $this->container->get('config.typed'),
      $this->container->get('module_handler'),
      $this->container->get('module_installer'),
      $this->container->get('theme_handler'),
      $this->container->get('string_translation'),
      $this->container->get('extension.list.module'),
      $this->container->get('extension.list.theme')
    );

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    // Fake a configuration import.
    $event_dispatcher->dispatch(new ConfigImporterEvent($config_importer), ConfigEvents::IMPORT);
    $this->assertSame(1, ArrayIncrement::$array['import_inc']);

    // Simulate the event of missing content.
    $event_dispatcher->dispatch(new MissingContentEvent([]), ConfigEvents::IMPORT_MISSING_CONTENT);
    $this->assertSame(1, ArrayIncrement::$array['import_inc']);
    $this->assertSame(1, ArrayIncrement::$array['import_missing_content_inc']);

    // Fake a configuration validate.
    $event_dispatcher->dispatch(new ConfigImporterEvent($config_importer), ConfigEvents::IMPORT_VALIDATE);
    $this->assertSame(1, ArrayIncrement::$array['import_inc']);
    $this->assertSame(1, ArrayIncrement::$array['import_missing_content_inc']);
    $this->assertSame(1, ArrayIncrement::$array['import_validate_inc']);

    // Simulate a config rename.
    $system_settings = \Drupal::configFactory()->getEditable('system.site');
    $event_dispatcher->dispatch(new ConfigRenameEvent($system_settings, 'system.site'), ConfigEvents::RENAME);
    $this->assertSame(1, ArrayIncrement::$array['import_inc']);
    $this->assertSame(1, ArrayIncrement::$array['import_missing_content_inc']);
    $this->assertSame(1, ArrayIncrement::$array['import_validate_inc']);
    $this->assertSame(1, ArrayIncrement::$array['import_rename_inc']);
  }

}
