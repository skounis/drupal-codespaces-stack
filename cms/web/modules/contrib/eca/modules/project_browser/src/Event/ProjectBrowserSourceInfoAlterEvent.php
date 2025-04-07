<?php

namespace Drupal\eca_project_browser\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is dispatched when project browser source plugin info alters.
 */
class ProjectBrowserSourceInfoAlterEvent extends Event {

  /**
   * Constructs the project browser source plugin info alter event.
   *
   * @param array $definitions
   *   The plugin definition.
   */
  public function __construct(
    protected array &$definitions,
  ) {}

  /**
   * Determines if the plugin with the given ID exists.
   *
   * @param string $id
   *   The plugin ID.
   *
   * @return bool
   *   TRUE, if the plugin exists, FALSE otherwise.
   */
  public function pluginExists(string $id): bool {
    return isset($this->definitions[$id]);
  }

  /**
   * Sets a property of the source plugin info.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $key
   *   The property key.
   * @param string $value
   *   The value of the property.
   * @param bool $localTask
   *   TRUE, if the key should be set for the local task, FALSE if the property
   *   is a generic one.
   */
  public function setProperty(string $id, string $key, string $value, bool $localTask = FALSE): void {
    if (!$this->pluginExists($id)) {
      return;
    }
    if ($localTask) {
      $this->definitions[$id]['local_task'][$key] = $value;
    }
    else {
      $this->definitions[$id][$key] = $value;
    }
  }

}
