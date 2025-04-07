<?php

declare(strict_types=1);

namespace Drupal\trash\Handler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\trash\TrashManagerInterface;

/**
 * Provides the default trash handler.
 */
class DefaultTrashHandler implements TrashHandlerInterface {

  use StringTranslationTrait;

  /**
   * The ID of the entity type managed by this handler.
   */
  protected string $entityTypeId;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * {@inheritdoc}
   */
  public function preTrashDelete(EntityInterface $entity): void {}

  /**
   * {@inheritdoc}
   */
  public function postTrashDelete(EntityInterface $entity): void {}

  /**
   * {@inheritdoc}
   */
  public function preTrashRestore(EntityInterface $entity): void {}

  /**
   * {@inheritdoc}
   */
  public function postTrashRestore(EntityInterface $entity): void {}

  /**
   * {@inheritdoc}
   */
  public function deleteFormAlter(array &$form, FormStateInterface $form_state, bool $multiple = FALSE): void {}

  /**
   * {@inheritdoc}
   */
  public function restoreFormAlter(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function purgeFormAlter(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeId(string $entity_type_id): static {
    $this->entityTypeId = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrashManager(TrashManagerInterface $trash_manager): static {
    $this->trashManager = $trash_manager;
    return $this;
  }

}
