<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\RecipeActivator;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectType;

/**
 * Tests the recipe activator. Obviously.
 *
 * @group project_browser
 * @covers \Drupal\project_browser\Activator\RecipeActivator
 */
final class RecipeActivatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'system'];

  /**
   * The activator under test.
   *
   * @var \Drupal\project_browser\Activator\RecipeActivator
   */
  private RecipeActivator $activator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->activator = $this->container->get(RecipeActivator::class);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->getDefinition(RecipeActivator::class)->setPublic(TRUE);
  }

  /**
   * Tests that Project Browser stores fully resolved paths of applied recipes.
   */
  public function testAbsoluteRecipePathIsStoredOnApply(): void {
    $base_dir = $this->getDrupalRoot() . '/core/tests/fixtures/recipes';
    if (!is_dir($base_dir)) {
      $this->markTestSkipped('This test requires a version of Drupal that supports recipes.');
    }
    $recipe = Recipe::createFromDirectory($base_dir . '/invalid_config/../no_extensions');
    RecipeRunner::processRecipe($recipe);

    $applied_recipes = $this->container->get(StateInterface::class)
      ->get('project_browser.applied_recipes', []);
    $this->assertContains($base_dir . '/no_extensions', $applied_recipes);
  }

  /**
   * Tests recipe activation with a project which is not installed physically.
   */
  public function testGetStatus(): void {
    $project = new Project(
      logo: NULL,
      isCompatible: TRUE,
      machineName: 'My Project',
      body: [],
      title: '',
      packageName: 'My Project',
      type: ProjectType::Recipe,
    );
    // As this project is not installed, RecipeActivator::getPath() will return
    // NULL, and therefore getStatus() will report the status as absent.
    $this->assertSame(ActivationStatus::Absent, $this->activator->getStatus($project));
  }

  /**
   * Tests that recipes' follow-up tasks are exposed by the activator.
   */
  public function testFollowUpTasks(): void {
    if (!method_exists(Recipe::class, 'getExtra')) {
      $this->markTestSkipped('This test requires Drupal 11.1.3 or later.');
    }

    // Enable the recipes source and prime the project cache.
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['recipes'])
      ->save();
    /** @var \Drupal\project_browser\EnabledSourceHandler $source_handler */
    $source_handler = $this->container->get(EnabledSourceHandler::class);
    $source_handler->getProjects('recipes');
    $project = $source_handler->getStoredProject('recipes/project-browser-test-recipe-with-tasks-recipe_with_tasks');
    // Tasks are not exposed unless the recipe has been applied.
    $this->assertEmpty($this->activator->getTasks($project));
    // Apply the recipe and ensure that the follow-up tasks are available.
    $this->activator->activate($project);
    $tasks = $this->activator->getTasks($project);
    $this->assertCount(2, $tasks);
    // Tasks can be unrouted URIs, or route names and parameters. Either way
    // should allow URL options.
    $this->assertSame('Visit Drupal.org', $tasks[0]->getText());
    $this->assertSame('https://drupal.org#hello', $tasks[0]->getUrl()->toString());
    $this->assertSame('Administer site compactly', $tasks[1]->getText());
    $this->assertStringEndsWith('/admin/compact/on?hi=there', $tasks[1]->getUrl()->toString());
  }

}
