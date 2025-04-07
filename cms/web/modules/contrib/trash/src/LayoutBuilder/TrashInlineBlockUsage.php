<?php

declare(strict_types=1);

namespace Drupal\trash\LayoutBuilder;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\trash\TrashManagerInterface;

/**
 * Decorates Layout Builder's inline block usage service.
 */
class TrashInlineBlockUsage implements InlineBlockUsageInterface {

  public function __construct(
    protected InlineBlockUsageInterface $inner,
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addUsage($block_content_id, EntityInterface $entity) {
    $this->inner->addUsage($block_content_id, $entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnused($limit = 100) {
    return $this->inner->getUnused($limit);
  }

  /**
   * {@inheritdoc}
   */
  public function removeByLayoutEntity(EntityInterface $entity) {
    $this->inner->removeByLayoutEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUsage(array $block_content_ids) {
    // Inline blocks are (permanently) deleted when they are no longer used in
    // any parent/referencing entity. With Trash, this can only happen when the
    // parent entity is purged, which means we have to ensure that its inline
    // blocks are purged as well instead of being trashed (soft-deleted).
    // Layout Builders handles this in
    // InlineBlockEntityOperations::deleteBlocksAndUsage(), but that method is
    // not public API, and it could be changed at any time, so the only good
    // option for Trash is to handle it in this service decorator.
    if ($this->trashManager->isEntityTypeEnabled('block_content')) {
      $this->trashManager->executeInTrashContext('inactive', function () use ($block_content_ids) {
        foreach ($block_content_ids as $block_content_id) {
          if ($block = BlockContent::load($block_content_id)) {
            $block->delete();
          }
        }
      });
    }

    $this->inner->deleteUsage($block_content_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsage($block_content_id) {
    return $this->inner->getUsage($block_content_id);
  }

}
