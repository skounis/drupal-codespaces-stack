<?php

namespace Drupal\trash;

use Drupal\workspaces\WorkspaceInterface;
use Drupal\wse_menu\WseMenuTreeStorage;

/**
 * Overrides the menu tree storage to provide workspace-specific menu trees.
 *
 * @internal
 */
class TrashWseMenuTreeStorage extends WseMenuTreeStorage {

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * {@inheritdoc}
   */
  public function setTrashManager(TrashManagerInterface $trash_manager): static {
    $this->trashManager = $trash_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildWorkspaceMenuTree(WorkspaceInterface $workspace, bool $replay_changes = TRUE): void {
    $this->trashManager->executeInTrashContext('ignore', fn () => parent::rebuildWorkspaceMenuTree($workspace, $replay_changes));
  }

}
