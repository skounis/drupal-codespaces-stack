<?php

namespace Drupal\focal_point\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\crop\CropInterface;
use Drupal\focal_point\FocalPointEffectBase;

/**
 * Crops image while keeping its focal point and the original height.
 *
 * @ImageEffect(
 *   id = "focal_point_crop_by_width",
 *   label = @Translation("Focal Point Crop by Width"),
 *   description = @Translation("Crops image while keeping its focal point and the original height.")
 * )
 */
class FocalPointCropByWidthImageEffect extends FocalPointEffectBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['height']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    parent::applyEffect($image);

    // Crop the image by keeping it's original height.
    $originalDimensions = $this->getOriginalImageSize();
    $crop = $this->getCrop($image);
    $crop->setSize($this->configuration['width'], $originalDimensions['height']);
    return $this->applyCropByWidth($image, $crop, $originalDimensions['height']);
  }

  /**
   * {@inheritdoc}
   */
  public function applyCropByWidth(ImageInterface $image, CropInterface $crop, $height) {
    // Get the top-left anchor position of the crop area.
    $anchor = $this->getAnchor($image, $crop);

    // Applying the same height as the original image.
    if (!$image->crop($anchor['x'], $anchor['y'], $this->configuration['width'], $height)) {
      $this->logger->error(
        'Focal Point Crop by Width failed while scaling and cropping using the %toolkit toolkit on %path (%mimetype, %dimensions, anchor: %anchor)',
        [
          '%toolkit' => $image->getToolkitId(),
          '%path' => $image->getSource(),
          '%mimetype' => $image->getMimeType(),
          '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
          '%anchor' => $anchor,
        ]
      );
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'crop_type' => 'focal_point',
    ];

  }

}
