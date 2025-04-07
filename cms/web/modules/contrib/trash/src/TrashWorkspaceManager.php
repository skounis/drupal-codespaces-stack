<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Provides a decorator for core's workspace manager service.
 */
class TrashWorkspaceManager implements WorkspaceManagerInterface {

  public function __construct(
    protected WorkspaceManagerInterface $inner,
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type) {
    if (method_exists($this->inner, 'isEntityTypeSupported')) {
      return $this->inner->isEntityTypeSupported($entity_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes() {
    if (method_exists($this->inner, 'getSupportedEntityTypes')) {
      return $this->inner->getSupportedEntityTypes();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasActiveWorkspace() {
    return $this->inner->hasActiveWorkspace();
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspace() {
    return $this->inner->getActiveWorkspace();
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    return $this->inner->setActiveWorkspace($workspace);
  }

  /**
   * {@inheritdoc}
   */
  public function switchToLive() {
    return $this->inner->switchToLive();
  }

  /**
   * {@inheritdoc}
   */
  public function executeInWorkspace($workspace_id, callable $function) {
    return $this->inner->executeInWorkspace($workspace_id, $function);
  }

  /**
   * {@inheritdoc}
   */
  public function executeOutsideWorkspace(callable $function) {
    return $this->inner->executeOutsideWorkspace($function);
  }

  /**
   * {@inheritdoc}
   */
  public function shouldAlterOperations(EntityTypeInterface $entity_type) {
    if (method_exists($this->inner, 'shouldAlterOperations')) {
      return $this->inner->shouldAlterOperations($entity_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function purgeDeletedWorkspacesBatch() {
    // Ensure that revisions are actually deleted when workspace data is purged.
    $this->trashManager->executeInTrashContext('inactive', function () {
      $this->inner->purgeDeletedWorkspacesBatch();
    });
  }

}
