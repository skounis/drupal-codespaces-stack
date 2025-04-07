<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to delete value from the shared temp store.
 *
 * @Action(
 *   id = "eca_sharedtempstore_delete",
 *   label = @Translation("Shared temporary store: delete"),
 *   description = @Translation("Delete a value from the Drupal shared temporary store by the given key."),
 *   eca_version_introduced = "2.1.5"
 * )
 */
class SharedTempStoreDelete extends SharedTempStoreRead {

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
