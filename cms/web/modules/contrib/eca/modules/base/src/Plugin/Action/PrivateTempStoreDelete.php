<?php

namespace Drupal\eca_base\Plugin\Action;

/**
 * Action to delete value from the private temp store.
 *
 * @Action(
 *   id = "eca_privatetempstore_delete",
 *   label = @Translation("Private temporary store: delete"),
 *   description = @Translation("Delete a value from the Drupal private temporary store by the given key."),
 *   eca_version_introduced = "2.1.5"
 * )
 */
class PrivateTempStoreDelete extends PrivateTempStoreRead {

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
