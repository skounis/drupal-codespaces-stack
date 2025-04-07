<?php

namespace Drupal\eca_content\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eca_content\Plugin\EntityReferenceSelection\EventBasedSelection;

/**
 * Dispatches on event-based entity reference selection.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class ReferenceSelection extends FieldSelectionBase {

  /**
   * The selection plugin instance.
   *
   * @var \Drupal\eca_content\Plugin\EntityReferenceSelection\EventBasedSelection
   */
  public EventBasedSelection $selection;

  /**
   * Constructs a new ReferenceSelection object.
   *
   * @param \Drupal\eca_content\Plugin\EntityReferenceSelection\EventBasedSelection $selection
   *   The selection plugin instance.
   */
  public function __construct(EventBasedSelection $selection) {
    $this->selection = $selection;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->selection->getConfiguration()['entity'];
  }

}
