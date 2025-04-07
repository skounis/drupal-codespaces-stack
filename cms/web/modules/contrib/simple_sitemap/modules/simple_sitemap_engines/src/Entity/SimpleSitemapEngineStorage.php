<?php

namespace Drupal\simple_sitemap_engines\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;

/**
 * Storage handler for simple_sitemap_engine configuration entities.
 */
class SimpleSitemapEngineStorage extends ConfigEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    $settings = $this->configFactory->getEditable('simple_sitemap_engines.settings');
    if (!empty($default = $settings->get('index_now_preferred_engine'))) {
      foreach ($entities as $entity) {
        if ($default === $entity->id()) {
          $settings->set('index_now_preferred_engine', NULL)->save();
          break;
        }
      }
    }
    parent::doDelete($entities);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    if (empty($entity->index_now_url)) {
      $settings = $this->configFactory->getEditable('simple_sitemap_engines.settings');
      if (!empty($default = $settings->get('index_now_preferred_engine'))
        && $default === $entity->id()) {
        $settings->set('index_now_preferred_engine', NULL)->save();
      }
    }

    return parent::doSave($id, $entity);
  }

}
