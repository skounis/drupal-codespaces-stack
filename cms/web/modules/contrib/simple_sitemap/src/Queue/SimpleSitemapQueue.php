<?php

namespace Drupal\simple_sitemap\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\DatabaseQueue;

/**
 * Defines a Simple XML Sitemap queue handler.
 */
class SimpleSitemapQueue extends DatabaseQueue {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * SimpleSitemapQueue constructor.
   *
   * @param string $name
   *   The name of the queue.
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct($name, Connection $connection, TimeInterface $time) {
    parent::__construct($name, $connection);
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   *
   * Unlike \Drupal\Core\Queue\DatabaseQueue::claimItem(), this method provides
   * a default lease time of 0 (no expiration) instead of 30. This allows the
   * item to be claimed repeatedly until it is deleted.
   */
  public function claimItem($lease_time = 0) {
    try {
      $item = $this->connection->queryRange('SELECT data, item_id FROM {queue} q WHERE name = :name ORDER BY item_id ASC', 0, 1, [':name' => $this->name])->fetchObject();
      if ($item) {
        $item->data = unserialize($item->data, ['allowed_classes' => FALSE]);
        return $item;
      }
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }

    return FALSE;
  }

  /**
   * Gets a simple_sitemap queue item in a very efficient way.
   *
   * @return \Generator
   *   A queue item object with at least the following properties:
   *   - data: the same as what what passed into createItem().
   *   - item_id: the unique ID returned from createItem().
   *   - created: timestamp when the item was put into the queue.
   *
   * @throws \Exception
   *
   * @see \Drupal\Core\Queue\QueueInterface::claimItem
   */
  public function yieldItem(): \Generator {
    try {
      $query = $this->connection->query('SELECT data, item_id FROM {queue} q WHERE name = :name ORDER BY item_id ASC', [':name' => $this->name]);
      while ($item = $query->fetchObject()) {
        $item->data = unserialize($item->data, ['allowed_classes' => FALSE]);
        yield $item;
      }
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

  /**
   * Adds a queue items and store it directly to the queue.
   *
   * @param mixed $data_sets
   *   Datasets to process.
   *
   * @return int|bool
   *   A unique ID if the item was successfully created and was (best effort)
   *   added to the queue, otherwise FALSE.
   */
  public function createItems($data_sets) {
    $try_again = FALSE;
    try {
      $id = $this->doCreateItems($data_sets);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if (!$try_again = $this->ensureTableExists()) {
        // If the exception happened for other reason than the missing table,
        // propagate the exception.
        throw $e;
      }
    }
    // Now that the table has been created, try again if necessary.
    if ($try_again) {
      $id = $this->doCreateItems($data_sets);
    }

    return $id;
  }

  /**
   * Adds a queue items and store it directly to the queue.
   *
   * @param mixed $data_sets
   *   Datasets to process.
   *
   * @return int|bool
   *   A unique ID if the item was successfully created and was (best effort)
   *   added to the queue, otherwise FALSE.
   */
  protected function doCreateItems($data_sets) {
    $query = $this->connection->insert(static::TABLE_NAME)
      ->fields(['name', 'data', 'created']);

    foreach ($data_sets as $data) {
      $query->values([
        $this->name,
        serialize($data),
        $this->time->getRequestTime(),
      ]);
    }

    return $query->execute();
  }

  /**
   * Deletes a finished items from the queue.
   *
   * @param mixed $item_ids
   *   Item IDs to delete.
   */
  public function deleteItems($item_ids): void {
    try {
      $this->connection->delete(static::TABLE_NAME)
        ->condition('item_id', $item_ids, 'IN')
        ->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

}
