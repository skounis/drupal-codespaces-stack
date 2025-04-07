<?php

namespace Drupal\editoria11y;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\editoria11y\Exception\Editoria11yApiException;

/**
 * Service description.
 */
class Api {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The manager property.
   */
  protected EntityTypeManager $manager;

  /**
   * Constructs an Api object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current User.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database.
   * @param \Drupal\Core\Entity\EntityTypeManager $manager
   *   Entity types.
   */
  public function __construct(AccountInterface $account, Connection $connection, EntityTypeManager $manager) {
    $this->account = $account;
    $this->connection = $connection;
    $this->manager = $manager;
  }

  /**
   * Function to test the results.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *    Invalid data.
   */
  public function testResults($results) {
    $now = time();
    // Confirm result_names is array?
    $this->validateNotNull($results["page_title"]);
    $this->validateNumber($results["page_count"]);
    $this->validateNumber($results["entity_id"]);
    $this->validatePath($results["page_path"]);

    // Reduce DB queries by only updating old records if some might exist.
    // @todo get the orGroup to work. Not found in this query class.
    /*$orGroupPagePath = $this->connection->orConditionGroup()
    ->condition('entity_id', $results['entity_id'])
    ->condition('page_path', $results["page_path"]);*/

    $checkOldDismissals = $this->connection->select('editoria11y_dismissals', 't');
    $checkOldDismissals->addExpression('COUNT(*)');
    $checkOldDismissals->condition('page_path', $results["page_path"]);
    $oldDismissals = $checkOldDismissals->execute()->fetchField();

    foreach ($results["results"] as $key => $value) {
      $this->validateNumber($value);

      // @todo handle page parameters that change content
      if ($results["page_count"] > 0) {
        $this->validateNotNull($key);
        $this->connection->merge("editoria11y_results")
                // Track the type and count of issues detected on this page.
          ->insertFields(
                  [
                    'page_title' => $results["page_title"],
                    'page_path' => $results["page_path"],
                    'entity_id' => $results["entity_id"],
                    'page_url' => $results["page_url"],
                    'page_language' => $results["language"],
                    'page_result_count' => $results["page_count"],
                    'entity_type' => $results["entity_type"],
                    'route_name' => $results["route_name"],
                    'result_name' => $key,
                    'result_name_count' => $value,
                    'updated' => $now,
                    'created' => $now,
                  ]
              )
                  // Update the "last seen" date of the page.
          ->updateFields(
                  [
                    'page_title' => $results["page_title"],
                    'page_path' => $results["page_path"],
                    'entity_id' => $results["entity_id"],
                    'page_url' => $results["page_url"],
                    'page_language' => $results["language"],
                    'page_result_count' => $results["page_count"],
                    'entity_type' => $results["entity_type"],
                    'route_name' => $results["route_name"],
                    'result_name' => $key,
                    'result_name_count' => $value,
                    'updated' => $now,
                  ]
              )
          ->keys(
                  [
                    'page_path' => $results["page_path"],
                    'result_name' => $key,
                  ]
              )
          ->execute();
      }

      // Update the last seen date for marked-as-hidden issues.
      // Note: v2.1.0 added entity_id, so we are updating it on sync for now.
      if ($oldDismissals > 0) {
        $this->connection->update("editoria11y_dismissals")
          ->fields(
                  [
                    'entity_id' => $results["entity_id"],
                    'stale' => 0,
                    'updated' => $now,
                  ]
              )
          ->condition('page_path', $results["page_path"])
          ->condition('result_name', $key)
          ->condition('route_name', $results["route_name"])
          ->execute();
      }
    }

    if ($oldDismissals > 0) {

      // Update the last seen date for marked-as-ok issues.
      // Marked-as-ok issues are not in the main results foreach.
      // Note: v2.1.0 added entity_id, so we are updating it on sync for now.
      foreach ($results["oks"] as $key => $value) {
        $this->validateNotNull($key);
        $this->connection->update("editoria11y_dismissals")
          ->fields(
                  [
                    'entity_id' => $results["entity_id"],
                    'stale' => 0,
                    'updated' => $now,
                  ]
              )
          ->condition('page_path', $results["page_path"])
          ->condition('result_name', $value)
          ->condition('route_name', $results["route_name"])
          ->execute();
      }

      // Set the stale flag for dismissals that were NOT updated.
      // We do not auto-delete them as some may come and go based on views.
      // @todo config or button to auto-delete stale items to prevent creep?
      // Note: v2.1.0 added entity_id, so we are updating it on sync for now.
      $this->connection->update("editoria11y_dismissals")
        ->fields(
                [
                  'stale' => 1,
                  'entity_id' => $results["entity_id"],
                ]
            )
        ->condition('page_path', $results["page_path"])
        ->condition('updated', $now, '!=')
        ->execute();
    }

    // Remove fixed issues at the right path.
    $this->connection->delete("editoria11y_results")
      ->condition('page_path', $results["page_path"])
      ->condition('updated', $now, '!=')
      ->execute();

    // Remove information at old aliases for this entity.
    if ($results['entity_id'] > 0) {
      $this->connection->delete("editoria11y_dismissals")
        ->condition('route_name', $results['route_name'])
        ->condition('entity_id', $results['entity_id'])
        ->condition('page_language', $results['language'])
        ->condition('page_path', $results['page_path'], '!=')
        ->execute();
      $this->connection->delete("editoria11y_results")
        ->condition('route_name', $results['route_name'])
        ->condition('entity_id', $results['entity_id'])
        ->condition('page_language', $results['language'])
        ->condition('page_path', $results['page_path'], '!=')
        ->execute();
    }

    Cache::invalidateTags(['editoria11y:dashboard']);
  }

  /**
   * The Purge page function.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  public function purgePage($page) {
    $this->validateNotNull($page["page_path"]);

    $this->connection->delete("editoria11y_dismissals")
      ->condition('page_path', $page["page_path"])
      ->execute();
    $this->connection->delete("editoria11y_results")
      ->condition('page_path', $page["page_path"])
      ->execute();
    // Clear cache for the referring page and dashboard.
    $invalidate = preg_replace('/[^a-zA-Z0-9]/', '', substr($page["page_path"], -80));
    Cache::invalidateTags(
        ['editoria11y:dismissals_' . $invalidate, 'editoria11y:dashboard']
      );
  }

  /**
   * The purge dismissal function.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  public function purgeDismissal($data) {
    $this->validateNotNull($data["page_path"]);
    $this->validateNotNull($data["result_name"]);

    $this->connection->delete("editoria11y_dismissals")
      ->condition('page_path', $data["page_path"])
      ->condition('result_name', $data["result_name"])
      ->condition('dismissal_status', $data["marked"])
      ->condition('uid', str_replace(",", "", $data["by"]))
      ->execute();
    // Clear cache for the referring page and dashboard.
    $invalidate = preg_replace('/[^a-zA-Z0-9]/', '', substr($data["page_path"], -80));
    Cache::invalidateTags(
      ['editoria11y:dismissals_' . $invalidate, 'editoria11y:dashboard']
    );
  }

  /**
   * The dismiss function.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  public function dismiss(string $operation, $dismissal) {
    $this->validatePath($dismissal["page_path"]);

    if ($operation == "reset") {
      // Reset ignores for the current user.
      $this->connection->delete("editoria11y_dismissals")
        ->condition('route_name', $dismissal["route_name"])
        ->condition('page_path', $dismissal["page_path"])
        ->condition('dismissal_status', "hide")
        ->condition('uid', $this->account->id())
        ->execute();
      if ($this->account->hasPermission('mark as ok in editoria11y')) {
        // Reset "Mark OK" for the super-user.
        $this->connection->delete("editoria11y_dismissals")
          ->condition('route_name', $dismissal["route_name"])
          ->condition('page_path', $dismissal["page_path"])
          ->condition('dismissal_status', "ok")
          ->execute();
      }
    }
    else {
      $this->validateDismissalStatus($operation);
      $this->validateNotNull($dismissal["result_name"]);
      $this->validateNotNull($dismissal["result_key"]);

      $now = time();

      $this->connection->merge("editoria11y_dismissals")
        ->insertFields(
                [
                  'page_path' => $dismissal["page_path"],
                  'page_title' => $dismissal["page_title"],
                  'route_name' => $dismissal["route_name"],
                  'entity_id' => $dismissal["entity_id"],
                  'entity_type' => $dismissal["entity_type"],
                  'page_language' => $dismissal["language"],
                  'uid' => $this->account->id(),
                  'element_id' => $dismissal["element_id"],
                  'result_name' => $dismissal["result_name"],
                  'result_key' => $dismissal["result_key"],
                  'dismissal_status' => $operation,
                  'created' => $now,
                  'updated' => $now,
                ]
            )
        ->updateFields(
                [
                  'page_path' => $dismissal["page_path"],
                  'page_title' => $dismissal["page_title"],
                  'route_name' => $dismissal["route_name"],
                  'entity_type' => $dismissal["entity_type"],
                  'entity_id' => $dismissal["entity_id"],
                  'page_language' => $dismissal["language"],
                  'uid' => $this->account->id(),
                  'element_id' => $dismissal["element_id"],
                  'result_name' => $dismissal["result_name"],
                  'result_key' => $dismissal["result_key"],
                  'dismissal_status' => $operation,
                  'updated' => $now,
                ]
            )
        ->keys(
                [
                  'element_id' => $dismissal["element_id"],
                  'result_name' => $dismissal["result_name"],
                  'entity_type' => $dismissal["entity_type"],
                  'route_name' => $dismissal["route_name"],
                  'page_path' => $dismissal["page_path"],
                  'page_language' => $dismissal["language"],
                ]
            )
        ->execute();
    }
    // Clear cache for the referring page and dashboard.
    $invalidate = preg_replace('/[^a-zA-Z0-9]/', '', substr($dismissal["page_path"], -80));
    Cache::invalidateTags(
          ['editoria11y:dismissals_' . $invalidate, 'editoria11y:dashboard']
      );
  }

  /**
   * This function to do validate of the elements.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  private function validateNotNull($user_input) {
    if (empty($user_input)) {
      throw new Editoria11yApiException("Missing value");
    }
  }

  /**
   * This function is used to validate the requested path.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  private function validatePath($user_input) {
    // @phpstan-ignore-next-line service call
    $config = \Drupal::config('editoria11y.settings');
    $prefix = $config->get('redundant_prefix');
    if (!empty($prefix) && strlen($prefix) < strlen($user_input) && strpos($user_input, $prefix) === 0) {
      // Replace ignorable subfolders.
      $altPath = substr_replace($user_input, "", 0, strlen($prefix));
      if (
        !(
          // @phpstan-ignore-next-line service call
          \Drupal::service('path.validator')->getUrlIfValid($altPath) ||
          // @phpstan-ignore-next-line service call
          \Drupal::service('path.validator')->getUrlIfValid($user_input)
        )
      ) {
        throw new Editoria11yApiException('Invalid page path on API report: "' . $user_input . '". If site is installed in subfolder, check Editoria11y config item "Syncing results to reports
--> Remove redundant base url from URLs"');
      }
    }
    else {
      // @phpstan-ignore-next-line (Why have services if you don't use them)
      if (!\Drupal::service('path.validator')->getUrlIfValid($user_input)) {
        throw new Editoria11yApiException('Invalid page path on API report: "' . $user_input . '". If site is installed in subfolder, check Editoria11y config item "Syncing results to reports
--> Remove redundant base url from URLs"');
      }
    }
  }

  /**
   * Validate dismissal status function.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  private function validateDismissalStatus($user_input) {
    if (!($user_input === 'ok' || $user_input === 'hide' || $user_input === 'reset')) {
      throw new Editoria11yApiException("Invalid dismissal operation: $user_input");
    }
  }

  /**
   * Validate number function.
   *
   * @throws \Drupal\editoria11y\Exception\Editoria11yApiException
   *   Invalid data.
   */
  private function validateNumber($user_input) {
    if (!(is_numeric($user_input))) {
      throw new Editoria11yApiException("Nan: $user_input");
    }
  }

}
