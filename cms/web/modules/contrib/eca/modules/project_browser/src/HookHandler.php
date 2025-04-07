<?php

namespace Drupal\eca_project_browser;

use Drupal\eca\Event\BaseHookHandler;

/**
 * The handler for hook implementations within the eca_misc.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * Triggers the event for when project browser source info get altered.
   *
   * @param array $definitions
   *   The list of source plugin definitions.
   */
  public function projectBrowserSourceInfo(array &$definitions): void {
    $this->triggerEvent->dispatchFromPlugin('project_browser:source_info_alter', $definitions);
  }

}
