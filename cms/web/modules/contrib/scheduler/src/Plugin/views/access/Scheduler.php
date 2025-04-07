<?php

namespace Drupal\scheduler\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provided access control for Scheduler views.
 *
 * This access plugin has been replaced by SchedulerRouteAccess, and is no
 * longer needed. However it has to remain (temporarily) as it is used in the
 * existing view. Deleting this class causes errors before the view can be
 * updated via update.php. The content below has been reduced to the minimum
 * necessary to avoid errors before update.php is run.
 *
 * @ViewsAccess(
 *   id = "scheduler",
 *   title = @Translation("Scheduled content access. REDUNDANT, DO NOT USE THIS."),
 *   help = @Translation("NOT USED"),
 * )
 */
class Scheduler extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
  }

}
