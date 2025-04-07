<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;

/**
 * Action to read value from key value store and store the result as a token.
 *
 * @Action(
 *   id = "eca_keyvaluestore_read",
 *   label = @Translation("Key value store: read"),
 *   description = @Translation("Reads a value from the Drupal key value store by the given key. The result is stored in a token."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class KeyValueStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): KeyValueStoreInterface {
    return $this->keyValueStoreFactory->get($collection);
  }

}
