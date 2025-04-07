<?php

namespace Drupal\eca_access\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when being asked for access to create an entity.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class CreateAccess extends Event implements AccessEventInterface {

  /**
   * An associative array of additional context values.
   *
   * @var array
   */
  protected array $context;

  /**
   * The entity bundle name.
   *
   * @var string
   */
  protected string $entityBundle;

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
   * @param array $context
   *   An associative array of additional context values. By default it contains
   *   language and the entity type ID:
   *   - entity_type_id - the entity type ID.
   *   - langcode - the current language code.
   * @param string $entity_bundle
   *   The entity bundle name.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that asks for access.
   */
  public function __construct(array $context, string $entity_bundle, AccountInterface $account) {
    $this->context = $context;
    $this->entityBundle = $entity_bundle;
    $this->account = $account;
  }

  /**
   * Get the additional context.
   *
   * @return array
   *   The additional context.
   */
  public function getContext(): array {
    return $this->context;
  }

  /**
   * Get the entity bundle name.
   *
   * @return string
   *   The entity bundle name.
   */
  public function getEntityBundle(): string {
    return $this->entityBundle;
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
  public function setAccessResult(AccessResultInterface $result): CreateAccess {
    $this->accessResult = $result;
    return $this;
  }

}
