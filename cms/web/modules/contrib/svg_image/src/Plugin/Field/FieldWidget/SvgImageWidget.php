<?php

namespace Drupal\svg_image\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Override plugin of the 'image_image' widget.
 *
 * We have to fully override standard field widget, so we will keep original
 * label and formatter ID.
 *
 * @FieldWidget(
 *   id = "image_image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class SvgImageWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $field_settings = $this->getFieldSettings();

    $allowedExtensions = $field_settings['file_extensions'];
    $supported_extensions = $this->imageFactory->getSupportedExtensions();
    $supported_extensions[] = 'svg';

    // If using custom extension validation, ensure that the extensions are
    // supported by the current image toolkit. Otherwise, validate against all
    // toolkit supported extensions.
    $extensions = !empty($allowedExtensions) ? array_intersect(explode(' ', $allowedExtensions), $supported_extensions) : $supported_extensions;
    // Remove default image validation. Otherwise, we get an error on upload:
    unset($element['#upload_validators']['FileIsImage']);

    $element['#upload_validators']['FileExtension']['extensions'] = implode(' ', $extensions);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      // Override image preview if SVG file.
      $file = reset($element['#files']);
      if (svg_image_is_file_svg($file)) {
        $element['preview'] = static::buildSvgPreview($file, $element['#preview_image_style']);
      }
    }
    elseif (!empty($element['#default_image'])) {
      // Override default image preview if SVG file.
      $file = File::load($element['#default_image']['fid']);
      if ($file && svg_image_is_file_svg($file)) {
        $element['preview'] = static::buildSvgPreview($file);
      }
    }

    return $element;
  }

  /**
   * Builds the SVG file preview.
   *
   * @param \Drupal\file\FileInterface $file
   *   The SVG file.
   * @param ?string $style_name
   *   The name of the image style to apply.
   *
   * @return array
   *   The render array.
   */
  protected static function buildSvgPreview(FileInterface $file, ?string $style_name = NULL): array {
    $dimensions = svg_image_get_image_file_dimensions($file);
    $file_uri = $file->getFileUri();
    if ($style_name) {
      $image_style = ImageStyle::load($style_name);
      $image_style->transformDimensions($dimensions, $file_uri);
    }
    return [
      '#weight' => -10,
      '#theme' => 'image',
      '#width' => $dimensions['width'],
      '#height' => $dimensions['height'],
      '#uri' => $file_uri,
    ];
  }

}
