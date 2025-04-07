<?php

namespace Drupal\svg_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\image\ImageStyleInterface;
use enshrined\svgSanitize\Sanitizer;

/**
 * SVG field formatter helper functions.
 */
trait SvgImageFormatterTrait {

  /**
   * Defines the default settings for this plugin.
   */
  protected static function svgDefaultSettings() {
    return [
      'svg_attributes' => ['width' => NULL, 'height' => NULL],
      'svg_render_as_image' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * Add SVG settings to the field formatter settings form.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The updated form array.
   */
  protected function svgSettingsForm(array $form) {

    $form['svg_render_as_image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render SVG image as &lt;img&gt;'),
      '#description' => $this->t('Render SVG images as usual image in IMG tag instead of &lt;svg&gt; tag'),
      '#default_value' => $this->getSetting('svg_render_as_image'),
    ];

    $form['svg_attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('SVG Images dimensions (attributes)'),
      '#tree' => TRUE,
    ];

    $form['svg_attributes']['width'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Width'),
      '#size' => 10,
      '#field_suffix' => 'px',
      '#default_value' => $this->getSetting('svg_attributes')['width'],
    ];

    $form['svg_attributes']['height'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Height'),
      '#size' => 10,
      '#field_suffix' => 'px',
      '#default_value' => $this->getSetting('svg_attributes')['height'],
    ];

    return $form;
  }

  /**
   * Provides content of the file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File to handle.
   *
   * @return string|bool
   *   File content or FALSE if the file does not exist or is invalid.
   */
  protected function fileGetContents(File $file) {
    $fileUri = $file->getFileUri();

    if (file_exists($fileUri)) {
      // Make sure that SVG is safe.
      $rawSvg = file_get_contents($fileUri);
      return (new Sanitizer())->sanitize($rawSvg);
    }

    $this->logger->error(
      'File @file_uri (ID: @file_id) does not exist in the filesystem or contains invalid XML.',
      ['@file_id' => $file->id(), '@file_uri' => $fileUri]
    );

    return FALSE;
  }

  /**
   * Renders a file as an img tag.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file.
   * @param array $element
   *   The build render array.
   * @param \Drupal\Core\Url|null $url
   *   An optional link.
   * @param \Drupal\image\ImageStyleInterface|null $imageStyle
   *   An optional image style for transforming image dimensions.
   * @param array|null $cacheTags
   *   Optional additional cache tags.
   */
  protected function renderAsImg(File $file, array &$element, ?Url $url = NULL, ?ImageStyleInterface $imageStyle = NULL, ?array $cacheTags = NULL): void {
    $attributes = $this->getSetting('svg_attributes');
    // Do not provide SVG dimensions when the width or height are already
    // provided. Otherwise, do provide them when the image style is set.
    // @see template_preprocess_image_style().
    if (empty($attributes['width']) && empty($attributes['height']) && $imageStyle) {
      // Determine the dimensions of the styled image.
      $dimensions = svg_image_get_image_file_dimensions($file);
      $imageStyle->transformDimensions($dimensions, $file->getFileUri());
      $attributes['width'] = $dimensions['width'];
      $attributes['height'] = $dimensions['height'];
    }

    // We have an SVG, but want to render it as an image.
    // So we keep the render array form the parent formatter, and remove
    // the image style.
    $element['#theme'] = 'image_formatter';
    $element['#image_style'] = NULL;
    $element['#item_attributes'] += $attributes;
    if ($cacheTags) {
      $element['#cache']['tags'] = Cache::mergeTags(
        $element['#cache']['tags'],
        $cacheTags
      );
    }
    if ($url) {
      $element['#url'] = $url;
    }
  }

  /**
   * Renders a file as raw SVG.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file.
   * @param array $element
   *   The build render array.
   * @param \Drupal\Core\Url|null $url
   *   An optional link.
   */
  protected function renderAsSvg(File $file, array &$element, ?Url $url): void {
    $svgRaw = $this->fileGetContents($file);
    if (!$svgRaw) {
      return;
    }
    $svgRaw = preg_replace(['/<\?xml.*\?>/i', '/<!DOCTYPE((.|\n|\r)*?)">/i'], '', $svgRaw);
    $markup = Markup::create(trim($svgRaw));

    if ($url) {
      $element = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $markup,
        '#cache' => [
          'tags' => $element['#cache']['tags'],
        ],
      ];
    }
    else {
      $element = [
        '#markup' => $markup,
        '#cache' => [
          'tags' => $element['#cache']['tags'],
        ],
      ];
    }
  }

}
