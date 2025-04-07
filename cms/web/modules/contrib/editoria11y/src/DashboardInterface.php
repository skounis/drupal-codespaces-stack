<?php

namespace Drupal\editoria11y;

use Drupal\Core\Database\StatementInterface;

/**
 * Handles database calls for DashboardController.
 */
interface DashboardInterface {

  /**
   * Gets dismissal options for select lists.
   *
   * @return array
   *   Return the dismissal value options.
   */
  public static function getDismissalOptions(): array;

  /**
   * Gets stale options for select lists.
   *
   * Note:
   * These are used in the "Still on page" filters, so the values are reversed.
   *
   * @return array
   *   Return the stale value options.
   */
  public static function getStaleOptions(): array;

  /**
   * Gets result name (issue types) options for select lists.
   *
   * @return array
   *   Return the result name value options.
   */
  public static function getResultNameOptions(): array;

  /**
   * Gets entity type options for select lists.
   *
   * @return array
   *   Return the entity type value options.
   */
  public static function getEntityTypeOptions(): array;

  /**
   * ExportPages function.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   Returns all results as individual rows.
   */
  public function exportPages(): StatementInterface;

  /**
   * Export dismissals function.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   Returns all results as individual rows.
   */
  public function exportDismissals(): StatementInterface;

  /**
   * Function to export the issues.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   Returns all results as individual rows
   */
  public function exportIssues(): StatementInterface;

}
