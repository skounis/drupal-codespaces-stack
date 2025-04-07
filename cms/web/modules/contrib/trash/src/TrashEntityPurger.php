<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Site\Settings;

/**
 * Purges content automatically when a given time has passed.
 */
class TrashEntityPurger {

  /**
   * The name of the queue that holds local items to be purged.
   *
   * @see \Drupal\trash\Plugin\QueueWorker\TrashEntityPurgeWorker
   */
  const PURGE_QUEUE_NAME = 'trash_entity_purge';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected QueueFactory $queueFactory,
    protected Settings $settings,
  ) {}

  /**
   * Populates the purge queue with items that have to be deleted.
   */
  public function populatePurgeQueue(string $entity_type_id): void {
    $now = $this->time->getCurrentTime();
    $config = $this->configFactory->get('trash.settings');
    $datetime = strtotime(sprintf('-%s', $config->get('auto_purge.after')), $now);

    $queue = $this->queueFactory->get(self::PURGE_QUEUE_NAME);

    $ids = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->addMetaData('trash', 'inactive')
      ->condition('deleted', $datetime, '<')
      ->execute();

    // Bail out early if there are no items to purge.
    if (empty($ids)) {
      return;
    }

    $chunks = array_chunk($ids, $this->settings->get('entity_update_batch_size', 50));
    foreach ($chunks as $chunk) {
      $queue->createItem([
        'batch' => $chunk,
        'entity_type_id' => $entity_type_id,
      ]);
    }
  }

  /**
   * Cron handler that will auto-purge content if enabled.
   */
  public function cronPurge(): void {
    $config = $this->configFactory->get('trash.settings');

    if ($config->get('auto_purge.enabled')) {
      foreach ($this->trashManager->getEnabledEntityTypes() as $entityTypeId) {
        $this->populatePurgeQueue($entityTypeId);
      }
    }
  }

}
