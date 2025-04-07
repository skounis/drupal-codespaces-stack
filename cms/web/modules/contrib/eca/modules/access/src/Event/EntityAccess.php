<?php

namespace Drupal\eca_access\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Event\EntityApplianceTrait;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when an entity is being asked for access.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class EntityAccess extends Event implements AccessEventInterface, EntityEventInterface {

  use EntityApplianceTrait;

  /**
   * The entity being asked for access.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The operation to perform.
   *
   * @var string
   */
  protected string $operation;

  /**
   * The account that asks for access.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The access result.
   *
   * @var \Drupal\Core\Access\AccessResultInterface|null
   */
  protected ?AccessResultInterface $accessResult = NULL;

  /**
   * An instance holding event data accessible as Token.
   *
   * @var \Drupal\eca\Plugin\DataType\DataTransferObject|null
   */
  protected ?DataTransferObject $eventData = NULL;

  /**
   * Constructs a new EntityAccess object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being asked for access.
   * @param string $operation
   *   The operation to perform.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that asks for access.
   */
  public function __construct(EntityInterface $entity, string $operation, AccountInterface $account) {
    $this->entity = $entity;
    $this->operation = $operation;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Get the operation to perform.
   *
   * @return string
   *   The operation.
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
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
  public function setAccessResult(AccessResultInterface $result): EntityAccess {
    $this->accessResult = $result;
    return $this;
  }

}
