<?php

namespace Drupal\eca_queue\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides task worker plugins for each distributed task queue.
 */
final class TaskWorkerDeriver implements ContainerDeriverInterface {

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected array $derivatives = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): TaskWorkerDeriver {
    return new TaskWorkerDeriver($container->get('entity_type.manager'));
  }

  /**
   * Constructs a new TaskWorkerDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    $this->getDerivativeDefinitions($base_plugin_definition);
    return $this->derivatives[$derivative_id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    // Also keep "eca_task" as is for non-distributed tasks.
    $this->derivatives[''] = $base_plugin_definition;

    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->entityTypeManager->getStorage('eca')->loadMultiple() as $eca) {
      foreach ($eca->get('events') ?? [] as $event) {
        if (($event['plugin'] ?? NULL) !== 'eca_queue:processing_task') {
          continue;
        }
        if (empty($event['configuration']['distribute']) || !isset($event['configuration']['task_name'])) {
          continue;
        }
        $task_name = TaskWorker::normalizeTaskName((string) $event['configuration']['task_name']);
        $this->derivatives[$task_name] = [
          'task_name' => $task_name,
          'title' => new TranslatableMarkup("ECA distributed @name tasks", ['@name' => $task_name]),
        ] + $base_plugin_definition;
        $cron_time = (int) ($event['configuration']['cron'] ?? 0);
        if ($cron_time > 0) {
          $this->derivatives[$task_name]['cron']['time'] = $cron_time;
        }
        else {
          unset($this->derivatives[$task_name]['cron']);
        }
      }
    }

    return $this->derivatives;
  }

}
