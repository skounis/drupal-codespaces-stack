<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\TempStore\SharedTempStore;

/**
 * Action to read value from shared temp store and store the result as a token.
 *
 * @Action(
 *   id = "eca_sharedtempstore_read",
 *   label = @Translation("Shared temporary store: read"),
 *   description = @Translation("Reads a value from the Drupal shared temporary store by the given key. The result is stored in a token."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SharedTempStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): SharedTempStore {
    return $this->sharedTempStoreFactory->get($collection);
  }

}
