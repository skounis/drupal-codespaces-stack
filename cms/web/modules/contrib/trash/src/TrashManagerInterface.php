<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\trash\Handler\TrashHandlerInterface;

/**
 * Provides an interface for the Trash manager.
 */
interface TrashManagerInterface {

  /**
   * Determines whether an entity type is supported by Trash.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type object.
   *
   * @return bool
   *   TRUE if the entity type is supported, FALSE otherwise.
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type): bool;

  /**
   * Determines whether Trash integration is enabled for an entity type/bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface|string $entity_type
   *   An entity type object or ID.
   * @param string|null $bundle
   *   (optional) The bundle name.
   *
   * @return bool
   *   TRUE if the entity type/bundle is enabled, FALSE otherwise.
   */
  public function isEntityTypeEnabled(EntityTypeInterface|string $entity_type, ?string $bundle = NULL): bool;

  /**
   * Gets the IDs of all entity types which can use the Trash.
   *
   * @return array
   *   An array of all trash-enabled entity type IDs.
   */
  public function getEnabledEntityTypes(): array;

  /**
   * Enables Trash integration for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type object.
   *
   * @throws \InvalidArgumentException
   *   Thrown when Trash integration can not be enabled or is already enabled
   *   for an entity type.
   */
  public function enableEntityType(EntityTypeInterface $entity_type): void;

  /**
   * Disables Trash integration for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type object.
   */
  public function disableEntityType(EntityTypeInterface $entity_type): void;

  /**
   * Determines whether entity and views queries should be altered.
   *
   * @return bool
   *   TRUE whether Trash should alter entity and views queries.
   */
  public function shouldAlterQueries(): bool;

  /**
   * Determines the current trash context.
   *
   * @return string
   *   One of 'active', 'inactive' or 'ignore'.
   */
  public function getTrashContext(): string;

  /**
   * Sets the current trash context.
   *
   * @param string $context
   *   One of 'active', 'inactive' or 'ignore'.
   *
   * @return $this
   */
  public function setTrashContext(string $context): static;

  /**
   * Executes the given callback function in a specific trash context.
   *
   * @param string $context
   *   One of 'active', 'inactive' or 'ignore'.
   * @param callable $function
   *   The callback to be executed.
   *
   * @return mixed
   *   The callable's return value.
   */
  public function executeInTrashContext($context, callable $function): mixed;

  /**
   * Gets the trash handler for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\trash\Handler\TrashHandlerInterface|null
   *   The trash handler for the given entity type, or NULL if the entity type
   *   is not enabled.
   *
   * @see \Drupal\trash\Handler\TrashHandlerPass
   */
  public function getHandler(string $entity_type_id): ?TrashHandlerInterface;

}
