<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

/**
 * Plugin implementation of the ECA condition of any user's permissions.
 *
 * @EcaCondition(
 *   id = "eca_user_permission",
 *   label = @Translation("User has permission"),
 *   description = @Translation("Checks, whether a given user account has a given permission."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class UserPermission extends CurrentUserPermission {

  use UserTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if ($account = $this->loadUserAccount()) {
      return $this->negationCheck($account->hasPermission($this->configuration['permission']));
    }
    return FALSE;
  }

}
