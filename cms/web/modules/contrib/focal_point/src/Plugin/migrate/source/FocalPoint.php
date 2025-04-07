<?php

namespace Drupal\focal_point\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * This source plugin migrates Focal Point.
 *
 * @MigrateSource(
 *   id = "focal_point",
 *   source_module = "focal_point"
 * )
 */
class FocalPoint extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'fid' => 'fid',
      'focal_point' => 'Focal point values.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'fid' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function query() {
    $query = $this->select('focal_point', 'fp');
    $query->fields('fp', ['fid', 'focal_point']);
    return $query;
  }

}
