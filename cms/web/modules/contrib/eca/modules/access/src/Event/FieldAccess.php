<?php

namespace Drupal\eca_access\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Dispatched when an entity field is being asked for access.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class FieldAccess extends EntityAccess {

  /**
   * The field name.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * Constructs a new FieldAccess object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being asked for access.
   * @param string $operation
   *   The operation to perform.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that asks for access.
   * @param string $field_name
   *   The field name.
   */
  public function __construct(EntityInterface $entity, string $operation, AccountInterface $account, string $field_name) {
    parent::__construct($entity, $operation, $account);
    $this->fieldName = $field_name;
  }

  /**
   * Get the field name.
   *
   * @return string
   *   The field name.
   */
  public function getFieldName(): string {
    return $this->fieldName;
  }

}
