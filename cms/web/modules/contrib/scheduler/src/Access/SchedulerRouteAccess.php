<?php

namespace Drupal\scheduler\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduler\SchedulerManager;
use Drupal\user\Entity\User;

/**
 * Sets access for scheduler views routes on the user page.
 */
class SchedulerRouteAccess implements AccessInterface {

  /**
   * The scheduler manager.
   *
   * @var \Drupal\scheduler\SchedulerManager
   */
  protected $schedulerManager;

  /**
   * Constructs a new SchedulerRouteAccess object.
   *
   * The scheduler.access_check service specifies the required argument.
   *
   * @param \Drupal\scheduler\SchedulerManager $scheduler_manager
   *   The scheduler manager.
   */
  public function __construct(SchedulerManager $scheduler_manager) {
    $this->schedulerManager = $scheduler_manager;
  }

  /**
   * Provides custom access checks for the scheduled views on the user page.
   *
   * A user is given access if either of the following conditions are met:
   * - they are viewing their own page and they have the permission to schedule
   * content or view scheduled content of the required type.
   * - they are viewing another user's page and they have permission to view
   * user profiles and view scheduled content, and the user they are viewing has
   * permission to schedule content or view scheduled content.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatchInterface $route_match) {
    $user_being_viewed = $route_match->getParameter('user');
    $viewing_own_page = $user_being_viewed == $account->id();

    // getUserPageViewRoutes() returns an array of user page view routes, keyed
    // on the entity id. Use this to get the entity id.
    $entityTypeId = array_search($route_match->getRouteName(), $this->schedulerManager->getUserPageViewRoutes());
    $viewing_permission_name = $this->schedulerManager->permissionName($entityTypeId, 'view');
    $scheduling_permission_name = $this->schedulerManager->permissionName($entityTypeId, 'schedule');

    if ($viewing_own_page && ($account->hasPermission($viewing_permission_name) || $account->hasPermission($scheduling_permission_name))) {
      return AccessResult::allowed();
    }
    if (!$viewing_own_page && $account->hasPermission($viewing_permission_name) && $account->hasPermission('access user profiles')) {
      $other_user = User::load($user_being_viewed);
      if ($other_user && ($other_user->hasPermission($viewing_permission_name) || $other_user->hasPermission($scheduling_permission_name))) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

}
