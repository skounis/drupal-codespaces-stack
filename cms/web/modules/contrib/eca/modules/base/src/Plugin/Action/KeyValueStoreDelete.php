<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to delete value from the key value store.
 *
 * @Action(
 *   id = "eca_keyvaluestore_delete",
 *   label = @Translation("Key value store: delete"),
 *   description = @Translation("Delete a value from the Drupal key value store by the given key."),
 *   eca_version_introduced = "2.1.5"
 * )
 */
class KeyValueStoreDelete extends KeyValueStoreRead {

  /**
   * {@inheritdoc}
   */
  protected function deleteMode(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsIfNotExists(): bool {
    return FALSE;
  }

}
