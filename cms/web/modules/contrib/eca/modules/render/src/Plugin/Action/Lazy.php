<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Build a lazy element.
 *
 * @Action(
 *   id = "eca_render_lazy",
 *   label = @Translation("Render: lazy element"),
 *   description = @Translation("Build a lazy render element, optionally with arguments."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Lazy extends RenderElementActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'argument' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $build['#type'] = 'eca_lazy';
    $build['#name'] = (string) $this->configuration['name'];
    $build['#argument'] = (string) $this->tokenService->replaceClear($this->configuration['argument']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Element name'),
      '#description' => $this->t('The name that identifies this element when reacting upon the event <em>ECA lazy element</em>.'),
      '#weight' => -200,
      '#default_value' => $this->configuration['name'],
      '#required' => TRUE,
    ];
    $form['argument'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Element argument'),
      '#description' => $this->t('Optionally specify an argument to be passed to the lazy element.'),
      '#weight' => -100,
      '#default_value' => $this->configuration['argument'],
      '#required' => FALSE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['argument'] = $form_state->getValue('argument');
  }

}
