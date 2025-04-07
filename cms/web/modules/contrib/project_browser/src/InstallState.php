<?php

namespace Drupal\project_browser;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\project_browser\ProjectBrowser\Project;

/**
 * Defines a service to track the installation state of projects.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class InstallState {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $keyValue;

  public function __construct(
    KeyValueFactoryInterface $keyValueFactory,
    private readonly TimeInterface $time,
  ) {
    $this->keyValue = $keyValueFactory->get('project_browser.install_status');
  }

  /**
   * Returns information on all in-progress project installs and timestamp.
   *
   * @param bool $include_timestamp
   *   Whether to include the `__timestamp` entry in the returned array.
   *   Defaults to FALSE.
   *
   * @return array
   *   The array contains:
   *   - Project states: Keyed by project ID, where each entry is an associative
   *     array containing:
   *       - source: The source plugin ID for the project.
   *       - status: The installation status of the project, or NULL if not set.
   *   - A separate `__timestamp` entry: The UNIX timestamp indicating when the
   *     request started (included only if $include_timestamp is TRUE).
   *
   *   Example return value:
   *   [
   *     'project_id1' => [
   *       'status' => 'requiring',
   *     ],
   *     'project_id2' => [
   *       'status' => 'installing',
   *     ],
   *     '__timestamp' => 1732086755,
   *   ]
   */
  public function toArray(bool $include_timestamp = FALSE): array {
    $data = $this->keyValue->getAll();
    if (!$include_timestamp) {
      unset($data['__timestamp']);
    }
    return $data;
  }

  /**
   * Sets project state and initializes a timestamp if not set.
   *
   * @param string $project_id
   *   The fully qualified ID of the project, in the form `SOURCE_ID/LOCAL_ID`.
   * @param string|null $status
   *   The installation status to set for the project, or NULL if no status.
   *   The status can be any arbitrary string, depending on the context
   *   or use case.
   */
  public function setState(string $project_id, ?string $status): void {
    $this->keyValue->setIfNotExists('__timestamp', $this->time->getRequestTime());
    if (is_string($status)) {
      $this->keyValue->set($project_id, ['status' => $status]);
    }
    else {
      $this->keyValue->delete($project_id);
    }
  }

  /**
   * Retrieves the install state of a project.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   The project object for which to retrieve the install state.
   *
   * @return string|null
   *   The current install status of the project, or NULL if not found.
   */
  public function getStatus(Project $project): ?string {
    $project_data = $this->keyValue->get($project->id);
    return $project_data['status'] ?? NULL;
  }

  /**
   * Deletes all project state data from key store.
   */
  public function deleteAll(): void {
    $this->keyValue->deleteAll();
  }

  /**
   * Retrieves the first updated time of the project states.
   *
   * @return int|null
   *   The timestamp when the project states were first updated, or NULL.
   */
  public function getFirstUpdatedTime(): ?int {
    return $this->keyValue->get('__timestamp');
  }

}
