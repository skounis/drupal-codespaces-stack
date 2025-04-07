<?php

namespace Drupal\eca_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\BaseHookHandler;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The handler for hook implementations within the eca_access.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * Trigger entity access.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being asked for access.
   * @param string $operation
   *   The operation to perform.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that asks for access.
   *
   * @return \Symfony\Contracts\EventDispatcher\Event|null
   *   The dispatched event, nor NULL if no event was dispatched.
   */
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?Event {
    return $this->triggerEvent->dispatchFromPlugin('access:entity', $entity, $operation, $account);
  }

  /**
   * Trigger entity field access.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being asked for access.
   * @param string $operation
   *   The operation to perform.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that asks for access.
   * @param string $field_name
   *   The field name.
   *
   * @return \Symfony\Contracts\EventDispatcher\Event|null
   *   The dispatched event, nor NULL if no event was dispatched.
   */
  public function fieldAccess(EntityInterface $entity, string $operation, AccountInterface $account, string $field_name): ?Event {
    return $this->triggerEvent->dispatchFromPlugin('access:field', $entity, $operation, $account, $field_name);
  }

  /**
   * Trigger entity create access.
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
   *
   * @return \Symfony\Contracts\EventDispatcher\Event|null
   *   The dispatched event, nor NULL if no event was dispatched.
   */
  public function createAccess(array $context, string $entity_bundle, AccountInterface $account): ?Event {
    return $this->triggerEvent->dispatchFromPlugin('access:create', $context, $entity_bundle, $account);
  }

}
