<?php

namespace Drupal\easy_email;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Email type entities.
 */
class EasyEmailTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Email type');
    $header['id'] = $this->t('Machine name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if (!empty($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Edit template');
    }

    $previewUrl = Url::fromRoute('entity.easy_email_type.preview', [
      'easy_email_type' => $entity->id(),
    ]);

    if ($previewUrl->access()) {
      $operations['preview'] = [
        'title' => $this->t('Preview template'),
        'weight' => 15,
        'url' => $previewUrl,
      ];
    }

    return $operations;
  }

}
