<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\eca\Plugin\Action\ListAddBase;

/**
 * Action to add a specified entity to a list.
 *
 * @Action(
 *   id = "eca_list_add_entity",
 *   label = @Translation("List: add entity"),
 *   description = @Translation("Add a specified entity to a list."),
 *   eca_version_introduced = "1.1.0",
 *   type = "entity"
 * )
 */
class ListAddEntity extends ListAddBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $this->addItem($entity);
  }

}
