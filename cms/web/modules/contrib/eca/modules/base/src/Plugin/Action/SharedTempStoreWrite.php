<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to write value to the shared temp store.
 *
 * @Action(
 *   id = "eca_sharedtempstore_write",
 *   label = @Translation("Shared temporary store: write"),
 *   description = @Translation("Write a value to the Drupal shared temporary store by the given key."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SharedTempStoreWrite extends SharedTempStoreRead {

  /**
   * {@inheritdoc}
   */
  protected function writeMode(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsIfNotExists(): bool {
    return FALSE;
  }

}
