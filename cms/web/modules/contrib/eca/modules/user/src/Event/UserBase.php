<?php

namespace Drupal\eca_user\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccountEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Abstract base class for user related events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_user\Event
 */
abstract class UserBase extends Event implements AccountEventInterface {

  /**
   * The account for the current event.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * ContentEntityBase constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for the current event.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

}
