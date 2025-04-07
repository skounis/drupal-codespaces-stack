<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectType;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Finder\Finder;

/**
 * Tests the source plugin that exposes locally installed recipes.
 *
 * @group project_browser
 * @covers \Drupal\project_browser\Plugin\ProjectBrowserSource\Recipes
 */
final class RecipesSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'project_browser_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('project_browser_test', [
      'project_browser_projects',
      'project_browser_categories',
    ]);
    $this->installConfig('project_browser_test');
    $this->installConfig('project_browser');
  }

  /**
   * Tests that recipes are discovered by the plugin.
   */
  public function testRecipesAreDiscovered(): void {
    $this->setSetting('extension_discovery_scan_tests', TRUE);

    /** @var \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface $source */
    $source = $this->container->get(ProjectBrowserSourceManager::class)->createInstance('recipes');

    // Generate a fake recipe in the temporary directory.
    $generated_recipe_name = uniqid();
    $generated_recipe_dir = FileSystem::getOsTemporaryDirectory() . '/' . $generated_recipe_name;
    mkdir($generated_recipe_dir);
    file_put_contents($generated_recipe_dir . '/composer.json', '{"name": "drupal/bogus_recipe"}');
    file_put_contents($generated_recipe_dir . '/recipe.yml', 'name: Bogus');

    $installed_recipes_dir = uniqid(FileSystem::getOsTemporaryDirectory() . '/');
    $file_system = new SymfonyFilesystem();
    $file_system->mkdir($installed_recipes_dir);
    // Symlink the fake recipe into the place where the source plugin will
    // search, to prove that the plugin follows symlinks.
    $file_system->symlink($generated_recipe_dir, $installed_recipes_dir . '/' . $generated_recipe_name);
    $this->setSetting('project_browser_recipe_directories', [$installed_recipes_dir]);

    $expected_recipe_names = [
      $generated_recipe_name,
      // Our test recipes should be discovered too.
      'test_recipe',
      'recipe_with_tasks',
    ];
    $finder = Finder::create()
      ->in($this->getDrupalRoot() . '/core/recipes')
      ->directories()
      ->notName('example')
      ->depth(0);
    foreach ($finder as $core_recipe) {
      $expected_recipe_names[] = $core_recipe->getBasename();
    }

    $projects = $source->getProjects();
    $found_recipes = [];
    foreach ($projects->list as $project) {
      $this->assertNotEmpty($project->title);
      $this->assertSame(ProjectType::Recipe, $project->type);
      $found_recipes[$project->machineName] = $project;
    }
    $found_recipe_names = array_keys($found_recipes);

    // The `example` recipe (from core) should always be hidden.
    $this->assertNotContains('example', $expected_recipe_names);

    sort($expected_recipe_names);
    sort($found_recipe_names);
    $this->assertSame($expected_recipe_names, $found_recipe_names);

    // Ensure the package names are properly resolved.
    $this->assertArrayHasKey('standard', $found_recipes);
    $this->assertSame('drupal/core', $found_recipes['standard']->packageName);
    $this->assertArrayHasKey('test_recipe', $found_recipes);
    $this->assertSame('project-browser-test/test-recipe', $found_recipes['test_recipe']->packageName);

    // The core recipes should have descriptions, which should become the body
    // text of the project.
    // The need for reflection sucks, but there's no way to introspect the body
    // on the backend.
    $body = (new \ReflectionProperty($found_recipes['standard'], 'body'))
      ->getValue($found_recipes['standard']);
    $this->assertNotEmpty($body);

    // Clean up.
    $file_system->remove([
      $installed_recipes_dir . '/' . $generated_recipe_name,
      $generated_recipe_dir,
    ]);
  }

  /**
   * Tests that discovered recipes are limited by an allow-list.
   */
  public function testAllowList(): void {
    $expected_recipe_names = ['document_media_type', 'user_picture'];

    $this->config('project_browser.admin_settings')
      ->set('allowed_projects', [
        'recipes' => ['example', ...$expected_recipe_names],
      ])
      ->save();

    /** @var \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface $source */
    $source = $this->container->get(ProjectBrowserSourceManager::class)->createInstance('recipes');
    $projects = $source->getProjects();
    $found_recipe_names = array_column($projects->list, 'machineName');

    // The `example` recipe (from core) should always be hidden, even if it's in
    // the allow list.
    $this->assertNotContains('example', $found_recipe_names);

    sort($expected_recipe_names);
    sort($found_recipe_names);
    $this->assertSame($expected_recipe_names, $found_recipe_names);
  }

  /**
   * Tests sorting of discovered recipes by case-insensitive name.
   */
  public function testRecipeSortingByRecipeName(): void {
    $this->setSetting('extension_discovery_scan_tests', TRUE);

    /** @var \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface $source */
    $source = $this->container->get(ProjectBrowserSourceManager::class)
      ->createInstance('recipes');

    // Generate fake recipes with varying case names.
    $generated_recipes = [
      'deltaRecipe' => '{"name": "drupal/delta_recipe"}',
      'betaRecipe' => '{"name": "drupal/beta_recipe"}',
      'AlphaRecipe' => '{"name": "drupal/alpha_recipe"}',
      'GammaRecipe' => '{"name": "drupal/gamma_recipe"}',
    ];

    $installed_recipes_dir = uniqid(FileSystem::getOsTemporaryDirectory() . '/');
    $file_system = new SymfonyFilesystem();
    $file_system->mkdir($installed_recipes_dir);

    foreach ($generated_recipes as $recipe_name => $composer_json_content) {
      $recipe_dir = $installed_recipes_dir . '/' . $recipe_name;
      $file_system->mkdir($recipe_dir);
      file_put_contents($recipe_dir . '/composer.json', $composer_json_content);
      file_put_contents($recipe_dir . '/recipe.yml', "name: $recipe_name");
    }

    $this->setSetting('project_browser_recipe_directories', [$installed_recipes_dir]);

    // Fetch discovered recipes.
    $projects = $source->getProjects();
    $found_recipes = array_column($projects->list, 'title');

    $generated_recipe_titles = array_keys($generated_recipes);
    // Filter the discovered recipe titles to include only those that
    // were generated during the test.
    $found_generated_titles = array_values(array_intersect($found_recipes, $generated_recipe_titles));

    // Sort the expected titles using case-insensitive sorting.
    usort($generated_recipe_titles, 'strcasecmp');

    $this->assertSame($generated_recipe_titles, $found_generated_titles);

    // Clean up.
    $file_system->remove($installed_recipes_dir);
  }

}
