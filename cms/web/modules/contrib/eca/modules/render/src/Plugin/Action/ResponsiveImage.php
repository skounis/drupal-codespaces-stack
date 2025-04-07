<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Build an image HTML element (responsive).
 *
 * @Action(
 *   id = "eca_render_responsive_image",
 *   label = @Translation("Render: responsive image"),
 *   description = @Translation("Build an image HTML element (responsive)."),
 *   eca_version_introduced = "1.1.0",
 *   deriver = "Drupal\eca_render\Plugin\Action\ResponsiveImageDeriver"
 * )
 */
class ResponsiveImage extends Image {

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    parent::doBuild($build);
    $build['#type'] = 'responsive_image';
    $build['#responsive_image_style_id'] = $build['#style_name'];
    unset($build['#style_name'], $build['#theme']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['style_name']['#title'] = $this->t('Responsive image style');
    $form['style_name']['#description'] = $this->t('Specify the configuration ID of the responsive image style.');
    $form['style_name']['#required'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $style_name = trim(($this->configuration['style_name'] ?? ''));
    if (($style_name !== '') && $image_style = $this->entityTypeManager->getStorage('responsive_image_style')->load($style_name)) {
      $dependencies[$image_style->getConfigDependencyKey()][] = $image_style->getConfigDependencyName();
    }
    return $dependencies;
  }

}
