<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduler\Traits\SchedulerMediaSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Action;

/**
 * Base class for the Scheduler Content Moderation tests.
 */
abstract class SchedulerContentModerationTestBase extends KernelTestBase {

  use ContentModerationTestTrait;
  use SchedulerMediaSetupTrait;
  use SchedulerSetupTrait;

  /**
   * Moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The moderation workflow.
   *
   * @var \Drupal\workflows\Entity\Workflow
   */
  protected $workflow;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'datetime',
    'field',
    'file',
    // Filter is needed for CreateNode filter_default_format().
    'filter',
    // Image is required to allow Media to be installed.
    'image',
    'language',
    'media',
    'node',
    'options',
    'scheduler',
    'scheduler_content_moderation_integration',
    'system',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig('content_moderation');
    $this->installConfig('filter');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');

    // Scheduler calls some config entity, instead of installing whole modules
    // default config just create the ones we need..
    DateFormat::create([
      'id' => 'long',
      'label' => 'Custom long date',
      'pattern' => 'l, F j, Y - H:i',
    ])->save();
    foreach (['node', 'media'] as $entityTypedId) {
      Action::create([
        'id' => "{$entityTypedId}_publish_action",
        'label' => "Custom {$entityTypedId} publish action",
        'type' => $entityTypedId,
        'plugin' => "entity:publish_action:{$entityTypedId}",
      ])->save();
      Action::create([
        'id' => "{$entityTypedId}_unpublish_action",
        'label' => "Custom {$entityTypedId} unpublish action",
        'type' => $entityTypedId,
        'plugin' => "entity:unpublish_action:{$entityTypedId}",
      ])->save();
    }

    // Define mediaStorage for use in SchedulerMediaSetupTrait functions.
    /** @var MediaStorageInterface $mediaStorage */
    $this->mediaStorage = $this->container->get('entity_type.manager')->getStorage('media');

    $this->configureExampleNodeType();
    $this->configureExampleMediaType();
    $this->configureEditorialWorkflow();

    $this->moderationInfo = \Drupal::service('content_moderation.moderation_information');
  }

  /**
   * Configure example node type.
   */
  protected function configureExampleNodeType() {
    $node_type = NodeType::create([
      'type' => 'example',
      'name' => 'Example content',
    ]);
    $node_type->setThirdPartySetting('scheduler', 'publish_enable', TRUE);
    $node_type->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE);
    $node_type->save();
  }

  /**
   * Configure example media type.
   */
  protected function configureExampleMediaType() {
    $media_type = $this->createMediaType('audio_file', [
      'id' => 'soundtrack',
      'label' => 'Sound track',
    ]);
    $media_type->setThirdPartySetting('scheduler', 'publish_enable', TRUE);
    $media_type->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE);
    $media_type->save();
  }

  /**
   * Configures the editorial workflow for the example node type.
   */
  protected function configureEditorialWorkflow() {
    $this->workflow = $this->createEditorialWorkflow();
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('media', 'soundtrack');
    $this->workflow->save();
  }

  /**
   * Test data for node and media entity types.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataEntityTypes() {
    $data = [
      '#node' => ['node', 'example'],
      '#media' => ['media', 'soundtrack'],
    ];
    return $data;
  }

}
