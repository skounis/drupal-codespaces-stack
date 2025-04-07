<?php

namespace Drupal\eca_content;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\BaseHookHandler;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * The handler for hook implementations within the eca_content.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * The entity types service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * Constructor.
   *
   * @param \Drupal\eca\Event\TriggerEvent $trigger_event
   *   The trigger event.
   * @param \Drupal\eca\Service\ContentEntityTypes $entityTypes
   *   The entity types Service.
   */
  public function __construct(TriggerEvent $trigger_event, ContentEntityTypes $entityTypes) {
    parent::__construct($trigger_event);
    $this->entityTypes = $entityTypes;
  }

  /**
   * Dispatches event bundle create.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   */
  public function bundleCreate(string $entity_type_id, string $bundle): void {
    $this->triggerEvent->dispatchFromPlugin('content_entity:bundlecreate', $entity_type_id, $bundle, $this->entityTypes);
  }

  /**
   * Dispatches event bundle delete.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   */
  public function bundleDelete(string $entity_type_id, string $bundle): void {
    $this->triggerEvent->dispatchFromPlugin('content_entity:bundledelete', $entity_type_id, $bundle, $this->entityTypes);
  }

  /**
   * Dispatches event create.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function create(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:create', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function delete(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:delete', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event field values init.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   */
  public function fieldValuesInit(FieldableEntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:fieldvaluesinit', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event insert.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function insert(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:insert', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event load.
   *
   * @param array $entities
   *   The entities.
   * @param string $entity_type_id
   *   The entity type id.
   */
  public function load(array $entities, string $entity_type_id): void {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $this->triggerEvent->dispatchFromPlugin('content_entity:load', $entity, $this->entityTypes);
      }
    }
  }

  /**
   * Dispatches event pre delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function predelete(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:predelete', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event pre load.
   *
   * @param array $ids
   *   The ids.
   * @param string $entity_type_id
   *   The entity type id.
   */
  public function preload(array $ids, string $entity_type_id): void {
    $this->triggerEvent->dispatchFromPlugin('content_entity:preload', $ids, $entity_type_id);
  }

  /**
   * Dispatches event prepare form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string|null $operation
   *   The operation.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function prepareForm(EntityInterface &$entity, ?string $operation, FormStateInterface $form_state): void {
    if ($entity instanceof ContentEntityInterface) {
      /** @var \Drupal\eca_content\Event\ContentEntityPrepareForm $event */
      $event = $this->triggerEvent->dispatchFromPlugin('content_entity:prepareform', $entity, $this->entityTypes, $operation, $form_state);
      $entity = $event->getEntity();
    }
  }

  /**
   * Dispatches event prepare view.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param array $entities
   *   The entities.
   * @param array $displays
   *   The displays.
   * @param string $view_mode
   *   The view mode.
   */
  public function prepareView(string $entity_type_id, array $entities, array $displays, string $view_mode): void {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $this->triggerEvent->dispatchFromPlugin('content_entity:prepareview', $entity, $this->entityTypes, $displays, $view_mode);
      }
    }
  }

  /**
   * Dispatches event pre save.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function presave(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:presave', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event revision create.
   *
   * @param \Drupal\Core\Entity\EntityInterface $new_revision
   *   The new revision.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool|null $keep_untranslatable_fields
   *   The untranslatable fields.
   */
  public function revisionCreate(EntityInterface $new_revision, EntityInterface $entity, ?bool $keep_untranslatable_fields): void {
    if ($new_revision instanceof ContentEntityInterface && $entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:revisioncreate', $new_revision, $this->entityTypes, $entity, $keep_untranslatable_fields);
    }
  }

  /**
   * Dispatches event revision delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function revisionDelete(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:revisiondelete', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event storage load.
   *
   * @param array $entities
   *   The entities.
   * @param string $entity_type
   *   The entity type service.
   */
  public function storageLoad(array $entities, string $entity_type): void {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $this->triggerEvent->dispatchFromPlugin('content_entity:storageload', $entity, $this->entityTypes);
      }
    }
  }

  /**
   * Dispatches event translation create.
   *
   * @param \Drupal\Core\Entity\EntityInterface $translation
   *   The translation.
   */
  public function translationCreate(EntityInterface $translation): void {
    if ($translation instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:translationcreate', $translation, $this->entityTypes);
    }
  }

  /**
   * Dispatches event translation delete.
   *
   * @param \Drupal\Core\Entity\EntityInterface $translation
   *   The translation.
   */
  public function translationDelete(EntityInterface $translation): void {
    if ($translation instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:translationdelete', $translation, $this->entityTypes);
    }
  }

  /**
   * Dispatches event translation insert.
   *
   * @param \Drupal\Core\Entity\EntityInterface $translation
   *   The translation.
   */
  public function translationInsert(EntityInterface $translation): void {
    if ($translation instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:translationinsert', $translation, $this->entityTypes);
    }
  }

  /**
   * Dispatches event update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function update(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      if ($entity->getEntityType()->hasKey('revision')) {
        // Make sure the subsequent actions will not create another revision
        // when they save this entity again.
        $entity->setNewRevision(FALSE);
        $entity->updateLoadedRevisionId();
      }
      $this->triggerEvent->dispatchFromPlugin('content_entity:update', $entity, $this->entityTypes);
    }
  }

  /**
   * Dispatches event view.
   *
   * @param array $build
   *   The build.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display.
   * @param string $view_mode
   *   The view mode.
   */
  public function view(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->triggerEvent->dispatchFromPlugin('content_entity:view', $entity, $this->entityTypes, $build, $display, $view_mode);
    }
  }

  /**
   * Dispatches event view mode alter.
   *
   * @param string $view_mode
   *   The view_mode that is to be used to display the entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being viewed.
   */
  public function viewModeAlter(string &$view_mode, EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      /** @var \Drupal\eca_content\Event\ContentEntityViewModeAlter $event */
      $event = $this->triggerEvent->dispatchFromPlugin('content_entity:viewmodealter', $entity, $this->entityTypes, $view_mode);
      $view_mode = $event->getViewMode();
    }
  }

}
