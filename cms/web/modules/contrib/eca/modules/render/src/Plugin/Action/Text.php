<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Build formatted text.
 *
 * @Action(
 *   id = "eca_render_text",
 *   label = @Translation("Render: text"),
 *   description = @Translation("Build a renderable text element."),
 *   eca_version_introduced = "1.1.0",
 *   deriver = "Drupal\eca_render\Plugin\Action\TextDeriver"
 * )
 */
class Text extends RenderElementActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'text' => '',
      'format' => 'plain_text',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->configuration['text'],
      '#required' => TRUE,
      '#weight' => 100,
      '#eca_token_replacement' => TRUE,
    ];
    $format_storage = $this->entityTypeManager->getStorage('filter_format');
    $format_options = [];
    foreach ($format_storage->loadMultiple() as $format) {
      $format_options[$format->id()] = $format->label();
    }
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter format'),
      '#options' => $format_options,
      '#default_value' => $this->configuration['format'],
      '#required' => TRUE,
      '#weight' => 110,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['text'] = $form_state->getValue('text', '');
    $this->configuration['format'] = $form_state->getValue('format');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $text = $this->tokenService->replaceClear($this->configuration['text']);
    $format = $this->configuration['format'] ?? '';
    if ($format === '_eca_token') {
      $format = $this->getTokenValue('format', 'plain_text');
    }
    if ($format === '') {
      $build = ['#markup' => $text];
    }
    else {
      $build = [
        '#type' => 'processed_text',
        '#text' => $text,
        '#format' => $format,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $dependencies['module'][] = 'filter';
    if ((($this->configuration['format'] ?? '') !== '') && $filter_format = $this->entityTypeManager->getStorage('filter_format')->load($this->configuration['format'])) {
      $dependencies[$filter_format->getConfigDependencyKey()][] = $filter_format->getConfigDependencyName();
    }
    return $dependencies;
  }

}
