<?php

namespace Drupal\svg_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageUrlFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image_url' formatter.
 *
 * Override default ImageUrlFormatter to proceed with svg urls.
 *
 * @FieldFormatter(
 *   id = "image_url",
 *   label = @Translation("URL to image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class SvgImageUrlFormatter extends ImageUrlFormatter {

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|null
   * @todo Our parent class will provide this in a future release.
   * @see https://www.drupal.org/project/drupal/issues/2811043
   */
  protected ?FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    $instance = parent::create($container, $configuration, $pluginId, $pluginDefinition);

    // Do not override the parent constructor to set extra class properties. The
    // constructor parameter order is different in different Drupal core
    // releases, even in minor releases in the same Drupal core version.
    $instance->fileUrlGenerator = $container->get('file_url_generator');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($elements)) {
      return $elements;
    }

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    $images = $this->getEntitiesToView($items, $langcode);
    $has_image_style = $this->getSetting('image_style');

    /** @var \Drupal\file\FileInterface[] $images */
    foreach ($images as $delta => $image) {
      if (svg_image_is_file_svg($image) && $has_image_style) {
        // Only change the URL if we're dealing with an SVG and an image style
        // is set.
        // Otherwise, keep the build of the parent formatter.
        $imageUri = $image->getFileUri();
        $elements[$delta]['#markup'] = $this->fileUrlGenerator->generateString($imageUri);
      }
    }

    return $elements;
  }

}
