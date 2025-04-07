<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\project_browser\ProjectBrowser\Normalizer;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @coversDefaultClass \Drupal\project_browser\ProjectBrowser\Normalizer
 * @group project_browser
 */
final class NormalizerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'field', 'system', 'user'];

  /**
   * Test that tasks returned by activators are filtered by user access.
   */
  public function testTasksAreFilteredByAccess(): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['drupal_core'])
      ->save();

    // Prime the project cache.
    /** @var \Drupal\project_browser\EnabledSourceHandler $source_handler */
    $source_handler = $this->container->get(EnabledSourceHandler::class);
    $source_handler->getProjects('drupal_core');
    $project = $source_handler->getStoredProject('drupal_core/field');

    $this->assertFalse(
      $this->container->get(AccountInterface::class)->hasPermission('administer modules'),
    );
    $normalizer = $this->container->get(Normalizer::class);
    $normalized = $normalizer->normalize($project, context: ['source' => 'drupal_core']);
    $this->assertEmpty($normalized['tasks']);

    // If we normalize with a user who can administer modules, we should get the
    // uninstall task.
    $this->installEntitySchema('user');
    $account = $this->createUser(['administer modules']);
    $normalized = $normalizer->normalize($project, context: [
      'source' => 'drupal_core',
      'account' => $account,
    ]);
    $this->assertSame('Uninstall', $normalized['tasks'][0]['text']);
  }

}
