<?php

namespace Drupal\eca_views\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\views\ViewExecutable;

/**
 * Dispatched when view with ECA access is being checked for access.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_views\Event
 */
class Access extends ViewsBase implements AccessEventInterface {

  /**
   * The user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public AccountInterface $account;

  /**
   * The access result.
   *
   * @var \Drupal\Core\Access\AccessResultInterface|null
   */
  protected ?AccessResultInterface $accessResult = NULL;

  /**
   * Constructs a new access event object.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function __construct(ViewExecutable $view, AccountInterface $account) {
    parent::__construct($view);
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessResult(): ?AccessResultInterface {
    return $this->accessResult;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessResult(AccessResultInterface $result): Access {
    $this->accessResult = $result;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

}
