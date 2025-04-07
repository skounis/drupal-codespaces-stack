<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\TempStore\PrivateTempStore;

/**
 * Action to read value from private temp store and store the result as a token.
 *
 * @Action(
 *   id = "eca_privatetempstore_read",
 *   label = @Translation("Private temporary store: read"),
 *   description = @Translation("Reads a value from the Drupal private temporary store by the given key. The result is stored in a token."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class PrivateTempStoreRead extends KeyValueStoreBase {

  /**
   * {@inheritdoc}
   */
  protected function store(string $collection): PrivateTempStore {
    return $this->privateTempStoreFactory->get($collection);
  }

}
