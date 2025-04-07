<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Action to read value from the expirable key value store.
 *
 * The result is being stored as a token.
 *
 * @Action(
 *   id = "eca_keyvalueexpirablestore_read",
 *   label = @Translation("Expirable key value store: read"),
 *   description = @Translation("Reads a value from the Drupal expirable key value store by the given key. The result is stored in a token."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class KeyValueExpirableStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): KeyValueStoreExpirableInterface {
    return $this->expirableKeyValueStoreFactory->get($collection);
  }

}
