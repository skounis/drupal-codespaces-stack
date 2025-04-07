<?php

declare(strict_types=1);

namespace Drupal\Tests\simple_sitemap\Kernel;

use Drupal\Core\Recipe\Recipe;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that simple_sitemap can be used in a recipe.
 */
class RecipeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'simple_sitemap',
    'simple_sitemap_engines',
    'simple_sitemap_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // @todo This check can be removed when Drupal 10.3 or later is required.
    if (!class_exists(Recipe::class)) {
      $this->markTestSkipped('This test requires a version of Drupal with recipe support.');
    }
    parent::setUp();
  }

  /**
   * Tests that the config shipped with Simple Sitemap is recipe-ready.
   */
  public function testRecipeCanBeInitializedWithExistingConfig(): void {
    $this->installSchema('simple_sitemap', 'simple_sitemap');
    $this->installConfig(static::$modules);

    $recipe = Recipe::createFromDirectory(__DIR__ . '/../../test_recipe');
    $this->assertIsObject($recipe);
  }

}
