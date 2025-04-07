<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Excludes soft-deleted entities.
 *
 * @SearchApiProcessor(
 *   id = "trash_status",
 *   label = @Translation("Trash status"),
 *   description = @Translation("Excludes entities that have been soft-deleted (moved to trash)."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class TrashStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    /** @var \Drupal\trash\TrashManagerInterface $trash_manager */
    $trash_manager = \Drupal::service('trash.manager');
    $entity_type_manager = \Drupal::entityTypeManager();

    // cspell:disable-next-line
    foreach ($index->getDatasources() as $data_source) {
      $entity_type_id = $data_source->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }
      $entity_type = $entity_type_manager->getDefinition($entity_type_id, FALSE);
      if ($entity_type && $trash_manager->isEntityTypeSupported($entity_type)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if ($object instanceof EntityInterface && trash_entity_is_deleted($object)) {
        unset($items[$item_id]);
      }
    }
  }

}
