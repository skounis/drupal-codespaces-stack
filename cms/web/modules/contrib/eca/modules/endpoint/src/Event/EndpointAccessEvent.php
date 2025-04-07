<?php

namespace Drupal\eca_endpoint\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;

/**
 * Dispatched when an ECA Endpoint is being checked for access.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_endpoint\Event
 */
class EndpointAccessEvent extends EndpointEventBase implements AccessEventInterface {

  /**
   * The arguments provided in the URL path.
   *
   * @var array
   */
  public array $pathArguments;

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
   * Constructs a new EcaRenderEndpointResponseEvent object.
   *
   * @param array &$path_arguments
   *   The arguments provided in the URL path.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Access\AccessResultInterface|null $access_result
   *   (optional) The predefined access result.
   */
  public function __construct(array &$path_arguments, AccountInterface $account, ?AccessResultInterface $access_result = NULL) {
    $this->pathArguments = &$path_arguments;
    $this->account = $account;
    $this->accessResult = $access_result;
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
  public function setAccessResult(AccessResultInterface $result): EndpointAccessEvent {
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
