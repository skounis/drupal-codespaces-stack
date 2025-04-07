<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\eca\Plugin\Action\ListDataOperationBase;

/**
 * Action to perform a save transaction on a list.
 *
 * @Action(
 *   id = "eca_list_save_data",
 *   label = @Translation("List: save data"),
 *   description = @Translation("Transaction to save contained data of a list into the database."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class ListSaveData extends ListDataOperationBase {

  /**
   * {@inheritdoc}
   */
  protected static string $operation = 'save';

}
