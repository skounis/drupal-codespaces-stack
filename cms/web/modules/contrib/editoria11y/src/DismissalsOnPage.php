<?php

namespace Drupal\editoria11y;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;

/**
 * Handles database calls for DashboardController.
 */
class DismissalsOnPage {

  /**
   * Database property.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a new connection object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database property.
   */
  public function __construct(Connection $connection) {
    $this->database = $connection;
  }

  /**
   * Function to get the dismissals.
   *
   * @param mixed $page_path
   *   Page path property.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   Return the dismissals.
   */
  public function getDismissals($page_path): ?StatementInterface {

    $query = $this->database->select('editoria11y_dismissals')
      ->fields('editoria11y_dismissals',
      ['uid',
        'result_key',
        'element_id',
        'dismissal_status',
        'page_path',
      ])
      ->condition('page_path', $page_path);
    return $query->execute();
  }

}
