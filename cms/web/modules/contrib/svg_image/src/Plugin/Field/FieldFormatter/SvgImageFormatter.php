<?php

namespace Drupal\svg_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * We have to fully override standard field formatter, so we will keep original
 * label and formatter ID.
 *
 * @FieldFormatter(
 *   id = "image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class SvgImageFormatter extends ImageFormatter {

  use SvgImageFormatterTrait;

  /**
   * File logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Do not override the parent constructor to set extra class properties. The
    // constructor parameter order is different in different Drupal core
    // releases, even in minor releases in the same Drupal core version.
    $instance->logger = $container->get('logger.channel.file');

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

    $imageStyleSetting = $this->getSetting('image_style');
    $imageStyle = NULL;
    if (!empty($imageStyleSetting)) {
      $imageStyle = $this->imageStyleStorage->load($imageStyleSetting);
    }

    foreach ($files as $delta => $file) {
      if (!svg_image_is_file_svg($file)) {
        // Simply keep the render array of the parent formatter for non-SVGs.
        continue;
      }

      if ($this->getSetting('svg_render_as_image')) {
        $this->renderAsImg($file, $elements[$delta], imageStyle: $imageStyle);
      }
      else {
        // Render as SVG tag.
        $this->renderAsSvg($file, $elements[$delta], $elements[$delta]['#url']);
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
