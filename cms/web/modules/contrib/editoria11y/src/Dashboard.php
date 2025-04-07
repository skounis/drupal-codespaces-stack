<?php

namespace Drupal\editoria11y;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;

/**
 * Handles database calls for DashboardController.
 */
class Dashboard implements DashboardInterface {
  /**
   * Database property.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * Constructs a dashboard object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database property.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritDoc}
   */
  public static function getDismissalOptions(): array {
    return [
      'hide' => t("hide"),
      'ok' => t('ok'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function getStaleOptions(): array {
    return [
      '0' => t("Yes"),
      '1' => t("No"),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function getResultNameOptions(): array {

    $database = \Drupal::database();

    $result_names = $database->select('editoria11y_dismissals', 't')
      ->fields('t', ['result_name'])
      ->groupBy('result_name')
      ->orderBy('result_name')
      ->execute()
      ->fetchCol();

    return array_combine($result_names, $result_names);
  }

  /**
   * {@inheritDoc}
   */
  public static function getEntityTypeOptions(): array {

    $database = \Drupal::database();

    $entity_types = $database->select('editoria11y_results', 't')
      ->fields('t', ['entity_type'])
      ->groupBy('entity_type')
      ->orderBy('entity_type')
      ->execute()
      ->fetchCol();

    return array_combine($entity_types, $entity_types);
  }

  /**
   * {@inheritDoc}
   */
  public function exportPages(): StatementInterface {

    $query = $this->database->select('editoria11y_results', 't');
    $query->fields('t', [
      'page_path',
      'page_title',
      'page_result_count',
      'entity_type',
      'page_language',
    ]);
    $query
      ->groupBy('page_path')
      ->groupBy('page_title')
      ->groupBy('page_result_count')
      ->groupBy('entity_type')
      ->groupBy('page_language');
    $query->orderBy('page_result_count', 'DESC');
    $query->orderBy('page_path');

    return $query
      ->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function exportIssues(): StatementInterface {

    $query = $this->database->select('editoria11y_results', 't');
    $query->fields('t', [
      'result_name',
      'page_path',
      'page_title',
      'entity_type',
      'page_language',
    ]);
    $query->orderBy('result_name');
    $query->orderBy('page_path');
    return $query
      ->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function exportDismissals(): StatementInterface {

    $query = $this->database->select('editoria11y_dismissals', 't')
      ->extend('Drupal\\Core\\Database\\Query\\TableSortExtender');
    $query->fields('t', ['
    page_title',
      'route_name',
      'page_path',
      'result_name',
      'page_language',
      'dismissal_status',
      'uid',
      'created',
      'stale',
    ])
      ->orderBy('page_path')
      ->orderBy('result_name');

    return $query->execute();
  }

}
