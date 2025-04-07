<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to delete value from the expirable key value store.
 *
 * @Action(
 *   id = "eca_keyvalueexpirablestore_delete",
 *   label = @Translation("Expirable key value store: delete"),
 *   description = @Translation("Deletes a value from the Drupal expirable key value store by the given key."),
 *   eca_version_introduced = "2.1.5"
 * )
 */
class KeyValueExpirableStoreDelete extends KeyValueExpirableStoreRead {

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
