<?php

namespace Drupal\svg_image_responsive\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;
use Drupal\svg_image\Plugin\Field\FieldFormatter\SvgImageFormatterTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'responsive_image' formatter.
 *
 * We have to fully override standard field formatter, so we will keep original
 * label and formatter ID.
 *
 * @FieldFormatter(
 *   id = "responsive_image",
 *   label = @Translation("Responsive image"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class SvgResponsiveImageFormatter extends ResponsiveImageFormatter {

  use SvgImageFormatterTrait;

  /**
   * File logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  private $logger;

  /**
   * File Url Generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->logger = $container->get('logger.channel.file');
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

    /** @var \Drupal\file\Entity\File[] $files */
    $files = $this->getEntitiesToView($items, $langcode);
    $imageLinkSetting = $this->getSetting('image_link');
    $imageStyle = NULL;
    $responsiveImageStyle = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    if ($responsiveImageStyle) {
      $imageStylesToLoad = $responsiveImageStyle->getImageStyleIds();
      $imageStyles = $this->imageStyleStorage->loadMultiple($imageStylesToLoad);
      $imageStyle = $imageStyles[$responsiveImageStyle->getFallbackImageStyle()] ?? NULL;
    }

    foreach ($files as $delta => $file) {
      if (!svg_image_is_file_svg($file)) {
        // Simply keep the render array of the parent formatter for non-SVGs.
        continue;
      }

      $url = NULL;
      if ($imageLinkSetting) {
        $url = $elements[$delta]['#url'];
        if ($imageLinkSetting === 'file') {
          $url = $this->fileUrlGenerator->generate($file->getFileUri());
        }
      }

      if ($this->getSetting('svg_render_as_image')) {
        $this->renderAsImg($file, $elements[$delta], $url, $imageStyle, $file->getCacheTags());
      }
      else {
        // Render as SVG tag.
        $this->renderAsSvg($file, $elements[$delta], $url);
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::svgDefaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    return $this->svgSettingsForm($form);
  }

}
