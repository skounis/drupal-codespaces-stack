<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

/**
 * Plugin implementation of the ECA condition of a user's id.
 *
 * @EcaCondition(
 *   id = "eca_user_id",
 *   label = @Translation("ID of user"),
 *   description = @Translation("Compares a user ID with a loaded ID of a given user account."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class UserId extends CurrentUserId {

  use UserTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if ($account = $this->loadUserAccount()) {
      // We need to cast the ID to string to avoid false positives when an
      // empty string value get compared to integer 0.
      $result = (string) $this->tokenService->replace($this->configuration['user_id']) === (string) $account->id();
      return $this->negationCheck($result);
    }
    return FALSE;
  }

}
