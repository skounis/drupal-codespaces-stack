<?php

namespace Drupal\checklistapi;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a class containing permission callbacks.
 */
class ChecklistapiPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of universal permissions.
   *
   * @return array
   *   An array of permission details.
   */
  public function universalPermissions() {
    $perms['view checklistapi checklists report'] = [
      'title' => $this->t('View the Checklists report'),
    ];
    $perms['view any checklistapi checklist'] = [
      'title' => $this->t('View any checklist'),
      'description' => $this->t('Read-only access: View list items and saved progress.'),
    ];
    $perms['edit any checklistapi checklist'] = [
      'title' => $this->t('Edit any checklist'),
      'description' => $this->t('Check and uncheck list items and save changes, or clear saved progress.'),
    ];
    return $perms;
  }

  /**
   * Returns an array of per checklist permissions.
   *
   * @return array
   *   An array of permission details.
   */
  public function perChecklistPermissions() {
    $perms = [];

    // Per checklist permissions.
    foreach (checklistapi_get_checklist_info() as $id => $definition) {
      $checklist = checklistapi_checklist_load($id);

      if (!$checklist) {
        continue;
      }

      $title = $checklist->title;
      $perms["view {$id} checklistapi checklist"] = [
        'title' => $this->t('View the @name checklist', ['@name' => $title]),
        'description' => $this->t('Read-only access: View list items and saved progress.'),
      ];
      $perms["edit {$id} checklistapi checklist"] = [
        'title' => $this->t('Edit the @name checklist', ['@name' => $title]),
        'description' => $this->t('Check and uncheck list items and save changes, or clear saved progress.'),
      ];
    }

    return $perms;
  }

}
