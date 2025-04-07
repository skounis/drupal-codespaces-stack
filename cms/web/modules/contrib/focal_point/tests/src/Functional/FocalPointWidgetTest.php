<?php

declare(strict_types=1);

namespace Drupal\Tests\focal_point\Functional;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests that the Focal Point widget works properly.
 *
 * @group focal_point
 */
class FocalPointWidgetTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use ImageFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'focal_point'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create an article content type that we will use for testing.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * {@inheritDoc}
   */
  public function testResave() {

    $field_name = strtolower($this->randomMachineName());

    class_exists(DeprecationHelper::class) ? DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      "10.3",
      fn() => $this->createImage($field_name),
      fn() => $this->createImage($field_name, TRUE)
    ) : $this->createImage($field_name, TRUE);

    // Find images that match our field settings.
    $images = $this->getTestFiles('image');
    /** @var \Drupal\file\Entity\File $image */
    $image = $images[0];

    // Create a File entity for the initial image.
    $file = File::create([
      'uri' => $image->uri,
      'uid' => 0,
    ]);
    $file->setPermanent();
    $file->save();

    // Use the first valid image to create a new Node.
    $image_factory = $this->container->get('image.factory');
    $image = $image_factory->get($image->uri);

    /** @var \Drupal\focal_point\FocalPointManagerInterface $focalPointManager */
    $focalPointManager = \Drupal::service('focal_point.manager');

    $crop = $focalPointManager->getCropEntity($file, 'focal_point');
    $focalPointManager->saveCropEntity(5, 5, $image->getWidth(), $image->getHeight(), $crop);

    $this->drupalCreateNode([
      'type' => 'article',
      'title' => t('Test Node'),
      $field_name => [
        'target_id' => $file->id(),
        'width' => $image->getWidth(),
        'height' => $image->getHeight(),
      ],
    ]);

    $crop = $focalPointManager->getCropEntity($file, 'focal_point');

    $this->assertEquals(2, $crop->get('x')->value);
    $this->assertEquals(1, $crop->get('y')->value);
    $this->assertEquals(0, $crop->get('width')->value);
    $this->assertEquals(0, $crop->get('height')->value);
  }

  /**
   * Function to create image field.
   *
   * @param string $field_name
   *   The field name to create.
   * @param bool $deprecated
   *   Decides if deprecated method call.
   */
  protected function createImage(string $field_name, bool $deprecated = FALSE): void {
    if ($deprecated) {
      $this->createImageField($field_name, 'article', [], [
        'file_extensions' => 'png jpg gif',
      ], [], [
        'image_style' => 'large',
        'image_link' => '',
      ]);
    }
    else {
      $this->createImageField($field_name, 'node', 'article', [
        'file_extensions' => 'png jpg gif',
      ], [], [
        'image_style' => 'large',
        'image_link' => '',
      ]);
    }
  }

}
