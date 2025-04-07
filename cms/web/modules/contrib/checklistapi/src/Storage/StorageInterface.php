<?php

namespace Drupal\checklistapi\Storage;

/**
 * Provides an interface for checklist storage.
 */
interface StorageInterface {

  /**
   * Sets the checklist ID.
   *
   * @param string $id
   *   The checklist ID.
   */
  public function setChecklistId($id);

  /**
   * Gets the saved checklist progress.
   *
   * @return mixed
   *   The stored value, or NULL if no value exists.
   */
  public function getSavedProgress();

  /**
   * Sets the saved checklist progress.
   *
   * @param array $progress
   *   An array of checklist progress data as built by ChecklistapiChecklist.
   */
  public function setSavedProgress(array $progress);

  /**
   * Deletes the saved checklist progress.
   */
  public function deleteSavedProgress();

}
