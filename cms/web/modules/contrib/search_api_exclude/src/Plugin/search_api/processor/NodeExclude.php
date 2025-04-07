<?php

namespace Drupal\search_api_exclude\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Defines a NodeExclude search_api processor plugin.
 *
 * @package Drupal\search_api_exclude\Plugin\search_api\processor
 *
 * @SearchApiProcessor(
 *   id = "node_exclude",
 *   label = @Translation("Node exclude"),
 *   description = @Translation("Exclude specific nodes"),
 *   stages = {
 *     "alter_items" = 0
 *   }
 * )
 */
class NodeExclude extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'node') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      $exclude = FALSE;
      if ($object instanceof NodeInterface) {
        /** @var \Drupal\node\NodeTypeInterface $type */
        $type = $object->type->entity;

        if ($type->getThirdPartySetting('search_api_exclude', 'enabled', FALSE)) {
          $exclude = (bool) $object->get('sae_exclude')->getString();
        }
      }

      if ($exclude) {
        unset($items[$item_id]);
      }
    }
  }

}
