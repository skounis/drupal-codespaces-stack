<?php

namespace Drupal\eca_queue\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_queue\Event\ProcessingTaskEvent;
use Drupal\eca_queue\QueueEvents;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation for ECA Queue events.
 *
 * @EcaEvent(
 *   id = "eca_queue",
 *   deriver = "Drupal\eca_queue\Plugin\ECA\Event\QueueEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class QueueEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'processing_task' => [
        'label' => 'ECA processing queued task',
        'event_name' => QueueEvents::PROCESSING_TASK,
        'event_class' => ProcessingTaskEvent::class,
        'tags' => Tag::RUNTIME,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if ($this->eventClass() === ProcessingTaskEvent::class) {
      $values = [
        'task_name' => '',
        'task_value' => '',
        'distribute' => FALSE,
        'cron' => '',
      ];
    }
    else {
      $values = [];
    }
    return $values + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($this->eventClass() === ProcessingTaskEvent::class) {
      $form['task_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Task name'),
        '#description' => $this->t('The task name will be used to identify, what type of task is to be processed. When multiple tasks are created that are of the same nature, they should share the same task name.'),
        '#default_value' => $this->configuration['task_name'],
        '#required' => TRUE,
      ];
      $form['task_value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Task value (optional)'),
        '#default_value' => $this->configuration['task_value'],
      ];
      $form['distribute'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Distribute: Process tasks of this name in their own queue.'),
        '#default_value' => $this->configuration['distribute'],
      ];
      $form['cron'] = [
        '#type' => 'number',
        '#title' => $this->t('Cron run time (seconds)'),
        '#description' => $this->t('<strong>Please note:</strong> This option is only available when the <em>Distribute</em> option is enabled above. Leave empty to disable processing when running cron.'),
        '#min' => 1,
        '#required' => FALSE,
        '#default_value' => $this->configuration['cron'],
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if ($this->eventClass() === ProcessingTaskEvent::class) {
      $this->configuration['task_name'] = $form_state->getValue('task_name');
      $this->configuration['task_value'] = $form_state->getValue('task_value');
      $this->configuration['distribute'] = !empty($form_state->getValue('distribute'));
      $this->configuration['cron'] = $form_state->getValue('cron', '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();
    $argument_task_name = isset($configuration['task_name']) ? mb_strtolower(trim($configuration['task_name'])) : '';
    $argument_task_value = isset($configuration['task_value']) ? mb_strtolower(trim($configuration['task_value'])) : '';
    if ($argument_task_name === '') {
      return '*';
    }
    $wildcard = $argument_task_name;
    if ($argument_task_value !== '') {
      $wildcard .= '::' . $argument_task_value;
    }
    return $wildcard;
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    /** @var \Drupal\eca_queue\Event\ProcessingTaskEvent $event */
    $task_name = mb_strtolower(trim((string) $event->getTask()->getTaskName()));
    $task_value = mb_strtolower(trim((string) $event->getTask()->getTaskValue()));
    return in_array($wildcard, [
      '*',
      $task_name,
      $task_name . '::' . $task_value,
    ], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function handleExceptions(): bool {
    return TRUE;
  }

}
