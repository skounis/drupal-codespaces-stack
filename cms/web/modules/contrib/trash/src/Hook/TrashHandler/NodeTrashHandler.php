<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\trash\Handler\DefaultTrashHandler;

/**
 * Provides a trash handler for the 'node' entity type.
 */
class NodeTrashHandler extends DefaultTrashHandler {

  /**
   * Implements hook_query_TAG_alter() for the 'search_node_search' tag.
   */
  #[Hook('query_search_node_search_alter')]
  public function querySearchNodeSearchAlter(AlterableInterface $query): void {
    // The core Search module is not using an entity query, so we need to alter
    // its query manually.
    // @see \Drupal\node\Plugin\Search\NodeSearch::findResults()
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query->isNull('n.deleted');
  }

}
