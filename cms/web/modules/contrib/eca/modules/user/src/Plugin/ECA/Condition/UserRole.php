<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

/**
 * Plugin implementation of the ECA condition of a user's role.
 *
 * @EcaCondition(
 *   id = "eca_user_role",
 *   label = @Translation("Role of user"),
 *   description = @Translation("Checks, whether a given user account has a given role."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class UserRole extends CurrentUserRole {

  use UserTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if ($account = $this->loadUserAccount()) {
      $userRoles = $account->getRoles();
      $result = in_array($this->configuration['role'], $userRoles, TRUE);
      return $this->negationCheck($result);
    }
    return FALSE;
  }

}
