<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\KernelTests\KernelTestBase;
use Drupal\block_content\BlockContentEvents;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\block_content\Event\BlockContentGetDependencyEvent;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;
use Drupal\layout_builder_test\Plugin\SectionStorage\SimpleConfigSectionStorage;
use Drupal\locale\LocaleEvent;
use Drupal\locale\LocaleEvents;

/**
 * Drupal core event tests provided by "eca_misc".
 *
 * @group eca
 * @group eca_misc
 */
class DrupalCoreEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'filter',
    'field',
    'block',
    'block_content',
    'file',
    'jsonapi',
    'layout_builder',
    'layout_builder_test',
    'language',
    'locale',
    'serialization',
    'eca',
    'eca_misc',
    'eca_test_array',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installEntitySchema('block_content');
    $this->installConfig(static::$modules);
    ConfigurableLanguage::create(['id' => 'de'])->save();
  }

  /**
   * Tests reacting upon kernel events.
   */
  public function testDrupalCoreEvents(): void {
    // This config does the following:
    // 1. It reacts upon all Drupal core events.
    // 2. It increments an array entry for each triggered event.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_drupal_core_events',
      'label' => 'ECA Drupal core events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'block_content_get_dependency' => [
          'plugin' => 'drupal:block_content_get_dependency',
          'label' => 'drupal block_content_get_dependency',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_block_content', 'condition' => ''],
          ],
        ],
        'file_upload_sanitize_name_event' => [
          'plugin' => 'drupal:file_upload_sanitize_name_event',
          'label' => 'drupal file_upload_sanitize_name_event',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_file_upload', 'condition' => ''],
          ],
        ],
        'select_page_display_variant' => [
          'plugin' => 'drupal:select_page_display_variant',
          'label' => 'drupal select_page_display_variant',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_select_page', 'condition' => ''],
          ],
        ],
        'build' => [
          'plugin' => 'drupal:build',
          'label' => 'drupal build',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_build', 'condition' => ''],
          ],
        ],
        'prepare_layout' => [
          'plugin' => 'drupal:prepare_layout',
          'label' => 'drupal prepare_layout',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_prepare_layout', 'condition' => ''],
          ],
        ],
        'section_component_build_render_array' => [
          'plugin' => 'drupal:section_component_build_render_array',
          'label' => 'drupal section_component_build_render_array',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_section_component', 'condition' => ''],
          ],
        ],
        'save_translation' => [
          'plugin' => 'drupal:save_translation',
          'label' => 'drupal save_translation',
          'configuration' => [],
          'successors' => [
            ['id' => 'increment_save_translation', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'increment_block_content' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'block_content_get_dependency',
          'configuration' => [
            'key' => 'block_content_get_dependency',
          ],
          'successors' => [],
        ],
        'increment_file_upload' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'file_upload_sanitize_name_event',
          'configuration' => [
            'key' => 'file_upload_sanitize_name_event',
          ],
          'successors' => [],
        ],
        'increment_select_page' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'select_page_display_variant',
          'configuration' => [
            'key' => 'select_page_display_variant',
          ],
          'successors' => [],
        ],
        'increment_build' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'build',
          'configuration' => [
            'key' => 'build',
          ],
          'successors' => [],
        ],
        'increment_prepare_layout' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'prepare_layout',
          'configuration' => [
            'key' => 'prepare_layout',
          ],
          'successors' => [],
        ],
        'increment_section_component' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'section_component_build_render_array',
          'configuration' => [
            'key' => 'section_component_build_render_array',
          ],
          'successors' => [],
        ],
        'increment_save_translation' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'save_translation',
          'configuration' => [
            'key' => 'save_translation',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');

    BlockContentType::create([
      'id' => 'type1',
      'label' => 'Type one',
      'revision' => FALSE,
    ])->save();
    $block_content = BlockContent::create([
      'info' => 'Hello',
      'type' => 'type1',
    ]);
    $block_content->save();
    $event = new BlockContentGetDependencyEvent($block_content);
    $event_dispatcher->dispatch($event, BlockContentEvents::BLOCK_CONTENT_GET_DEPENDENCY);
    $this->assertSame(1, ArrayIncrement::$array['block_content_get_dependency']);

    $event = new FileUploadSanitizeNameEvent('foo.txt', '');
    $event_dispatcher->dispatch($event);
    $this->assertSame(1, ArrayIncrement::$array['file_upload_sanitize_name_event']);

    $event = new PageDisplayVariantSelectionEvent('simple_page', \Drupal::routeMatch());
    $event_dispatcher->dispatch($event, RenderEvents::SELECT_PAGE_DISPLAY_VARIANT);
    $this->assertSame(1, ArrayIncrement::$array['select_page_display_variant']);

    $event = ResourceTypeBuildEvent::createFromEntityTypeAndBundle(\Drupal::entityTypeManager()->getDefinition('user'), 'user', []);
    $event_dispatcher->dispatch($event, ResourceTypeBuildEvents::BUILD);
    $this->assertSame(1, ArrayIncrement::$array['build']);

    $definition = new SectionStorageDefinition(['id' => 'test_simple_config']);
    $section_storage = SimpleConfigSectionStorage::create($this->container, [], 'test_simple_config', $definition);
    $section_storage->setContext('config_id', new Context(new ContextDefinition('string'), 'foobar'));

    $event = new PrepareLayoutEvent($section_storage);
    $event_dispatcher->dispatch($event, LayoutBuilderEvents::PREPARE_LAYOUT);
    $this->assertSame(1, ArrayIncrement::$array['prepare_layout']);

    $section_component = new SectionComponent('first-uuid', 'content', ['id' => 'foo'], ['bar' => 'baz']);
    $event = new SectionComponentBuildRenderArrayEvent($section_component, [], FALSE);
    $event_dispatcher->dispatch($event, LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY);
    $this->assertSame(1, ArrayIncrement::$array['section_component_build_render_array']);

    $event_dispatcher->dispatch(new LocaleEvent(['de']), LocaleEvents::SAVE_TRANSLATION);
    $this->assertSame(1, ArrayIncrement::$array['save_translation']);
  }

}
