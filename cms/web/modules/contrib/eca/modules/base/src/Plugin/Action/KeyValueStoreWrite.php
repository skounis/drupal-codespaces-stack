<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to write value to the key value store.
 *
 * @Action(
 *   id = "eca_keyvaluestore_write",
 *   label = @Translation("Key value store: write"),
 *   description = @Translation("Write a value to the Drupal key value store by the given key."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class KeyValueStoreWrite extends KeyValueStoreRead {

  /**
   * {@inheritdoc}
   */
  protected function writeMode(): bool {
    return TRUE;
  }

}
