<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Functional;

use Drupal\project_browser\EnabledSourceHandler;
use Drupal\project_browser_test\Plugin\ProjectBrowserSource\ProjectBrowserTestMock;
use Drupal\Tests\BrowserTestBase;

/**
 * @covers \Drupal\project_browser\EnabledSourceHandler
 * @group project_browser
 */
final class EnabledSourceHandlerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['project_browser_test_mock', 'drupal_core'])
      ->save(TRUE);
  }

  /**
   * Tests that trying to load a previously unseen project throws an exception.
   */
  public function testExceptionOnGetUnknownProject(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Project 'sight/unseen' was not found in non-volatile storage.");

    $this->container->get(EnabledSourceHandler::class)
      ->getStoredProject('sight/unseen');
  }

  /**
   * Tests loading a previously seen project.
   */
  public function testGetStoredProject(): void {
    $handler = $this->container->get(EnabledSourceHandler::class);

    $project = $handler->getProjects('project_browser_test_mock')->list[0];

    $project_again = $handler->getStoredProject('project_browser_test_mock/' . $project->id);
    $this->assertNotSame($project, $project_again);
    $this->assertSame($project->toArray(), $project_again->toArray());
  }

  /**
   * Tests that query results are not stored if there was an error.
   */
  public function testErrorsAreNotStored(): void {
    /** @var \Drupal\project_browser\EnabledSourceHandler $handler */
    $handler = $this->container->get(EnabledSourceHandler::class);
    $handler->getProjects('project_browser_test_mock');

    $has_cached_queries = function (): bool {
      $items = $this->container->get('keyvalue')
        ->get('project_browser:project_browser_test_mock')
        ->getAll();
      return (bool) array_filter(
        array_keys($items),
        fn (string $key): bool => str_starts_with($key, 'query:'),
      );
    };
    // Query results should have been stored.
    $this->assertTrue($has_cached_queries());

    $handler->clearStorage();
    ProjectBrowserTestMock::$resultsError = 'Nope!';

    $handler->getProjects('project_browser_test_mock');
    // No query results should have been stored.
    $this->assertFalse($has_cached_queries());
  }

  /**
   * Tests that the install profile is ignored by the drupal_core source.
   */
  public function testProfileNotListedByCoreSource(): void {
    $result = $this->container->get(EnabledSourceHandler::class)->getProjects('drupal_core');
    // Assert that the current install profile is not returned by the source.
    $this->assertNotContains($this->profile, array_column($result->list, 'machineName'));
  }

}
