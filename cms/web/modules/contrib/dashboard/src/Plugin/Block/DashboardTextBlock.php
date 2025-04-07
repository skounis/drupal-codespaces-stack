<?php

namespace Drupal\dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Dashboard Text block.
 *
 * @Block(
 *   id = "dashboard_text_block",
 *   admin_label = @Translation("Dashboard Text"),
 *   category = @Translation("Dashboard"),
 * )
 */
final class DashboardTextBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'text' => [
        'value' => '',
        'format' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Text'),
      '#format' => $this->configuration['text']['format'],
      '#default_value' => $this->configuration['text']['value'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['text'] = $form_state->getValue('text');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['content'] = [
      '#type' => 'processed_text',
      '#text' => $this->configuration['text']['value'],
      '#format' => $this->configuration['text']['format'],
    ];
    return $build;
  }

}
