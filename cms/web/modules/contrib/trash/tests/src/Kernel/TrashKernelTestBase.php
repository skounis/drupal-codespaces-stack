<?php

namespace Drupal\Tests\trash\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\trash\TrashManagerInterface;

/**
 * Base class for Trash kernel tests.
 */
abstract class TrashKernelTestBase extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'image',
    'node',
    'media',
    'text',
    'trash',
    'trash_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');
    $this->installEntitySchema('trash_test_entity');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['node', 'filter', 'trash_test']);

    $this->createContentType(['type' => 'article']);
    $this->createContentType(['type' => 'page']);

    $config = \Drupal::configFactory()->getEditable('trash.settings');
    $enabled_entity_types = $config->get('enabled_entity_types');
    $enabled_entity_types['trash_test_entity'] = [];
    $enabled_entity_types['node'] = ['article'];
    $config->set('enabled_entity_types', $enabled_entity_types);
    $config->save();

    // Rebuild the container so trash handlers are available for the enabled
    // entity types.
    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Gets the trash manager.
   */
  protected function getTrashManager(): TrashManagerInterface {
    return \Drupal::service('trash.manager');
  }

}
