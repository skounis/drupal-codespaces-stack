<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Build a details element.
 *
 * @Action(
 *   id = "eca_render_details",
 *   label = @Translation("Render: HTML details"),
 *   description = @Translation("Build a HTML details element."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Details extends RenderElementActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'title' => '',
      'open' => FALSE,
      'introduction_text' => '',
      'summary_value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $build = [
      '#type' => 'details',
      '#value' => $this->tokenService->replaceClear($this->configuration['summary_value']),
      '#title' => $this->tokenService->replaceClear($this->configuration['title']),
      '#open' => $this->configuration['open'],
    ];

    if ($this->configuration['introduction_text'] !== '') {
      $introduction_text = (string) $this->tokenService->replaceClear($this->configuration['introduction_text']);
      if ($introduction_text !== '') {
        $build['introduction_text'] = [
          '#type' => 'markup',
          '#prefix' => '<div class="introduction-text">',
          '#markup' => $introduction_text,
          '#suffix' => '</div>',
          '#weight' => -1000,
        ];
      }
    }
    if ($this->configuration['summary_value'] !== '') {
      $summary_value = (string) $this->tokenService->replaceClear($this->configuration['summary_value']);
      if ($summary_value !== '') {
        $build['#value'] = $summary_value;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This will be shown to the user in the form as grouping title.'),
      '#weight' => -9,
      '#default_value' => $this->configuration['title'],
      '#required' => TRUE,
    ];
    $form['open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open'),
      '#default_value' => $this->configuration['open'],
      '#weight' => -8,
      '#required' => FALSE,
    ];
    $form['introduction_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introduction text'),
      '#weight' => -5,
      '#default_value' => $this->configuration['introduction_text'],
      '#required' => FALSE,
    ];
    $form['summary_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Summary value'),
      '#weight' => -4,
      '#default_value' => $this->configuration['summary_value'],
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['open'] = !empty($form_state->getValue('open'));
    $this->configuration['introduction_text'] = $form_state->getValue('introduction_text');
    $this->configuration['summary_value'] = $form_state->getValue('summary_value');
  }

}
