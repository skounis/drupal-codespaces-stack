<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to write value from the private temp store.
 *
 * @Action(
 *   id = "eca_privatetempstore_write",
 *   label = @Translation("Private temporary store: write"),
 *   description = @Translation("Write a value to the Drupal private temporary store by the given key."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class PrivateTempStoreWrite extends PrivateTempStoreRead {

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
