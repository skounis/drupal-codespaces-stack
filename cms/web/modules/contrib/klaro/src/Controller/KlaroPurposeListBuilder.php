<?php

namespace Drupal\klaro\Controller;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Klaro! purposes.
 */
class KlaroPurposeListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $limit = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'klaro_purpose_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID', [], ['context' => 'klaro']);
    $header['label'] = $this->t('Label', [], ['context' => 'klaro']);
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\klaro\KlaroPurposeInterface $entity */
    $row['id']['#markup'] = $entity->id();
    $row['label'] = $entity->label();

    return $row + parent::buildRow($entity);
  }

}
