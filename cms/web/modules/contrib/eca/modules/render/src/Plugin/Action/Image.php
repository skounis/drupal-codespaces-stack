<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * Build an image HTML element (not responsive).
 *
 * @Action(
 *   id = "eca_render_image",
 *   label = @Translation("Render: image"),
 *   description = @Translation("Build an image HTML element (not responsive)."),
 *   eca_version_introduced = "1.1.0",
 *   deriver = "Drupal\eca_render\Plugin\Action\ImageDeriver"
 * )
 */
class Image extends RenderElementActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'uri' => '',
      'style_name' => '',
      'alt' => '',
      'title' => '',
      'width' => '',
      'height' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $uri = trim((string) $this->tokenService->replaceClear($this->configuration['uri']));
    if ($uri === '') {
      throw new \InvalidArgumentException("No URI given for rendering an image element.");
    }
    $style_name = trim((string) $this->tokenService->replaceClear($this->configuration['style_name']));
    $build = [
      '#theme' => $style_name === '' ? 'image' : 'image_style',
      '#uri' => $uri,
      '#style_name' => $style_name,
      '#width' => $this->tokenService->replaceClear($this->configuration['width']),
      '#height' => $this->tokenService->replaceClear($this->configuration['height']),
      '#alt' => $this->tokenService->replaceClear($this->configuration['alt']),
      '#title' => $this->tokenService->replaceClear($this->configuration['title']),
      '#attributes' => [
        'id' => Html::getUniqueId('eca-image'),
        'class' => ['eca-image'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI'),
      '#description' => $this->t('Either the relative path of the file or a full URL.'),
      '#weight' => -100,
      '#default_value' => $this->configuration['uri'],
      '#required' => TRUE,
    ];
    $form['style_name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Style name'),
      '#description' => $this->t('Optionally, specify the configuration ID of the image style to use.'),
      '#weight' => -90,
      '#default_value' => $this->configuration['style_name'],
      '#required' => FALSE,
    ];
    $form['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alt text'),
      '#description' => $this->t('Specify the alternative text for text-based browsers.'),
      '#weight' => -80,
      '#default_value' => $this->configuration['alt'],
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Optionally specify the value of the title attribute.'),
      '#weight' => -70,
      '#default_value' => $this->configuration['title'],
      '#required' => FALSE,
    ];
    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Optionally specify the value of the width attribute.'),
      '#weight' => -60,
      '#default_value' => $this->configuration['width'],
      '#required' => FALSE,
    ];
    $form['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Optionally specify the value of the height attribute.'),
      '#weight' => -50,
      '#default_value' => $this->configuration['height'],
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['uri'] = $form_state->getValue('uri');
    $this->configuration['style_name'] = $form_state->getValue('style_name');
    $this->configuration['alt'] = $form_state->getValue('alt');
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['width'] = $form_state->getValue('width');
    $this->configuration['height'] = $form_state->getValue('height');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $style_name = trim(($this->configuration['style_name'] ?? ''));
    if (($style_name !== '') && $image_style = $this->entityTypeManager->getStorage('image_style')->load($style_name)) {
      $dependencies[$image_style->getConfigDependencyKey()][] = $image_style->getConfigDependencyName();
    }
    return $dependencies;
  }

}
