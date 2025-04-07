<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_image\Functional;

use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\BrowserTestBase;

/**
 * @group drupal_cms_image
 */
class ComponentValidationTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function test(): void {
    $dir = realpath(__DIR__ . '/../../..');

    // The recipe should apply cleanly.
    $this->applyRecipe($dir);
    // Apply it again to prove that it is idempotent.
    $this->applyRecipe($dir);

    // Ensure all image styles convert to WebP.
    $image_styles = ImageStyle::loadMultiple();
    foreach ($image_styles as $id => $image_style) {
      $this->assertSame('webp', $image_style->getDerivativeExtension('png'), "The '$id' image style does not convert to WebP.");
    }
  }

}
