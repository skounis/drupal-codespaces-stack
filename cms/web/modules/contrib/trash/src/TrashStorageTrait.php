<?php

namespace Drupal\trash;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Provides the ability to soft-delete entities at the storage level.
 */
trait TrashStorageTrait {

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    if ($this->getTrashManager()->getTrashContext() !== 'active') {
      parent::delete($entities);
      return;
    }

    $to_delete = [];
    $to_trash = [];

    foreach ($entities as $entity) {
      if ($this->getTrashManager()->isEntityTypeEnabled($entity->getEntityType(), $entity->bundle())) {
        $to_trash[] = $entity;
      }
      else {
        $to_delete[] = $entity;
      }
    }

    parent::delete($to_delete);

    $field_name = 'deleted';
    $revisionable = $this->getEntityType()->isRevisionable();

    foreach ($to_trash as $entity) {
      // Allow code to run before soft-deleting.
      $this->getTrashManager()->getHandler($this->entityTypeId)->preTrashDelete($entity);
      $this->invokeHook('pre_trash_delete', $entity);

      $entity->set($field_name, \Drupal::time()->getRequestTime());

      // Always create a new revision if the entity type is revisionable.
      if ($revisionable) {
        /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
        $entity->setNewRevision(TRUE);

        if ($entity instanceof RevisionLogInterface) {
          $entity->setRevisionUserId(\Drupal::currentUser()->id());
          $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        }
      }
      $entity->save();

      // Allow code to run after soft-deleting.
      $this->getTrashManager()->getHandler($this->entityTypeId)->postTrashDelete($entity);
      $this->invokeHook('trash_delete', $entity);
    }
  }

  /**
   * Restores soft-deleted entities.
   *
   * @param array $entities
   *   An array of entity objects to restore.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function restoreFromTrash(array $entities) {
    $field_name = 'deleted';
    $revisionable = $this->getEntityType()->isRevisionable();

    foreach ($entities as $entity) {
      // Allow code to run before restoring from trash.
      $this->getTrashManager()->getHandler($this->entityTypeId)->preTrashRestore($entity);
      $this->invokeHook('pre_trash_restore', $entity);

      $entity->set($field_name, NULL);

      // Always create a new revision if the entity type is revisionable.
      if ($revisionable) {
        /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
        $entity->setNewRevision(TRUE);

        if ($entity instanceof RevisionLogInterface) {
          $entity->setRevisionUserId(\Drupal::currentUser()->id());
          $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        }
      }
      $entity->save();

      // Allow code to run after restoring from trash.
      $this->getTrashManager()->getHandler($this->entityTypeId)->postTrashRestore($entity);
      $this->invokeHook('trash_restore', $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildQuery($ids, $revision_ids = FALSE) {
    $query = parent::buildQuery($ids, $revision_ids);

    if ($this->getTrashManager()->getTrashContext() !== 'active') {
      return $query;
    }

    if (!$revision_ids
      && $this->getWorkspaceInformation()?->isEntityTypeSupported($this->entityType)
      && ($active_workspace = $this->getWorkspaceManager()?->getActiveWorkspace())
    ) {
      // Join the workspace_association table so we can select possible
      // workspace-specific revisions.
      $wa_join = $query->leftJoin('workspace_association', NULL, "[%alias].[target_entity_type_id] = '{$this->entityTypeId}' AND [%alias].[target_entity_id] = [base].[{$this->idKey}] AND [%alias].[workspace] = :active_workspace_id", [
        ':active_workspace_id' => $active_workspace->id(),
      ]);

      // Joins must be in order. i.e, any tables you mention in the ON clause of
      // a JOIN must appear prior to that JOIN. So we must ensure that the new
      // 'workspace_association' table appears prior to the 'revision' one.
      $tables =& $query->getTables();
      $revision = $tables['revision'];
      unset($tables['revision']);
      $tables['revision'] = $revision;

      $tables['revision']['condition'] = "[revision].[{$this->revisionKey}] = COALESCE([$wa_join].[target_entity_revision_id], [base].[{$this->revisionKey}])";
    }

    $table_mapping = $this->getTableMapping();
    $deleted_column = $table_mapping->getFieldColumnName($this->fieldStorageDefinitions['deleted'], 'value');

    // Ensure that entity_load excludes deleted entities.
    if ($revision_data = $this->getRevisionDataTable()) {
      $query->join($revision_data, 'revision_data', "[revision_data].[{$this->revisionKey}] = [revision].[{$this->revisionKey}]");
      $query->condition("revision_data.$deleted_column", NULL, 'IS NULL');
    }
    elseif ($this->getRevisionTable()) {
      $query->condition("revision.$deleted_column", NULL, 'IS NULL');
    }
    elseif ($data_table = $this->getDataTable()) {
      $query->join($data_table, 'data', "[data].[{$this->idKey}] = [base].[{$this->idKey}]");
      $query->condition("data.$deleted_column", NULL, 'IS NULL');
    }
    else {
      $query->condition("base.$deleted_column", NULL, 'IS NULL');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    // Ensure that deleted entities are never stored in the persistent cache.
    foreach ($entities as $id => $entity) {
      if (trash_entity_is_deleted($entity)) {
        unset($entities[$id]);
      }
    }
    if (empty($entities)) {
      return;
    }

    parent::setPersistentCache($entities);
  }

  /**
   * {@inheritdoc}
   */
  protected function getStorageSchema() {
    if (!isset($this->storageSchema)) {
      $class = $this->entityType->getHandlerClass('storage_schema') ?: 'Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema';

      // Ensure that we use our generated storage schema class.
      $class = _trash_generate_storage_class($class, 'storage_schema');

      $this->storageSchema = new $class($this->entityTypeManager, $this->entityType, $this, $this->database, $this->entityFieldManager);
    }
    return $this->storageSchema;
  }

  /**
   * Gets the trash manager service.
   */
  private function getTrashManager(): TrashManagerInterface {
    return \Drupal::service('trash.manager');
  }

  /**
   * Gets the workspace manager service.
   */
  private function getWorkspaceManager(): ?WorkspaceManagerInterface {
    return \Drupal::hasService('workspaces.manager') ? \Drupal::service('workspaces.manager') : NULL;
  }

  /**
   * Gets the workspace information service.
   */
  private function getWorkspaceInformation(): ?WorkspaceInformationInterface {
    return \Drupal::hasService('workspaces.information') ? \Drupal::service('workspaces.information') : NULL;
  }

}
