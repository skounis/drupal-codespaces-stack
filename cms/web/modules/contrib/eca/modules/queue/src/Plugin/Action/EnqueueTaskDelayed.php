<?php

namespace Drupal\eca_queue\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Enqueue a Task with a delay.
 *
 * @Action(
 *   id = "eca_enqueue_task_delayed",
 *   label = @Translation("Enqueue a task with a delay"),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class EnqueueTaskDelayed extends EnqueueTask {

  use PluginFormTrait;

  public const DELAY_SECONDS = 1;
  public const DELAY_MINUTES = 60;
  public const DELAY_HOURS = 3600;
  public const DELAY_DAYS = 86400;
  public const DELAY_WEEKS = 604800;
  public const DELAY_MONTHS = 2592000;

  /**
   * {@inheritdoc}
   */
  protected function getEarliestProcessingTime(): int {
    $delay_unit = $this->configuration['delay_unit'];
    if ($delay_unit === '_eca_token') {
      $delay_unit = $this->getTokenValue('delay_unit', '');
    }
    return $this->time->getCurrentTime() +
      (int) $this->tokenService->replaceClear($this->configuration['delay_value']) * (int) $delay_unit;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'delay_value' => '1',
      'delay_unit' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['delay_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delay value'),
      '#default_value' => $this->configuration['delay_value'],
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    $form['delay_unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Delay unit'),
      '#default_value' => $this->configuration['delay_unit'],
      '#options' => [
        static::DELAY_SECONDS => $this->t('seconds'),
        static::DELAY_MINUTES => $this->t('minutes'),
        static::DELAY_HOURS => $this->t('hours'),
        static::DELAY_DAYS => $this->t('days'),
        static::DELAY_WEEKS => $this->t('weeks'),
        static::DELAY_MONTHS => $this->t('months'),
      ],
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['delay_value'] = $form_state->getValue('delay_value');
    $this->configuration['delay_unit'] = $form_state->getValue('delay_unit');
    parent::submitConfigurationForm($form, $form_state);
  }

}
