<?php

declare(strict_types=1);

namespace Drupal\trash\Handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\trash\TrashManagerInterface;

/**
 * Provides an interface for trash handlers.
 */
interface TrashHandlerInterface {

  /**
   * Acts before an entity is soft-deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   */
  public function preTrashDelete(EntityInterface $entity): void;

  /**
   * Acts after an entity is soft-deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   */
  public function postTrashDelete(EntityInterface $entity): void;

  /**
   * Acts before an entity is restored.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   */
  public function preTrashRestore(EntityInterface $entity): void;

  /**
   * Acts after an entity is restored.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   */
  public function postTrashRestore(EntityInterface $entity): void;

  /**
   * Alters the entity delete form to provide additional information if needed.
   *
   * @param array $form
   *   The entity form to be altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param bool $multiple
   *   (optional) Whether multiple entities are being deleted by the form.
   *   Defaults to FALSE.
   */
  public function deleteFormAlter(array &$form, FormStateInterface $form_state, bool $multiple = FALSE): void;

  /**
   * Alters the entity restore form to provide additional information if needed.
   *
   * @param array $form
   *   The entity form to be altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function restoreFormAlter(array &$form, FormStateInterface $form_state): void;

  /**
   * Alters the entity purge form to provide additional information if needed.
   *
   * @param array $form
   *   The entity form to be altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function purgeFormAlter(array &$form, FormStateInterface $form_state): void;

  /**
   * Sets the ID of the entity type managed by this handler.
   */
  public function setEntityTypeId(string $entity_type_id): static;

  /**
   * Sets the entity type manager service.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static;

  /**
   * Sets the trash manager service.
   */
  public function setTrashManager(TrashManagerInterface $trash_manager): static;

}
