<?php

namespace Drupal\eca_cache\Plugin\Action;

/**
 * Trait provides helper functions for cache related actions.
 */
trait EcaCacheTrait {

  /**
   * {@inheritdoc}
   */
  protected function getBackendOptions(): array {
    return [
      'eca_default' => $this->t('Default shared cache'),
      'eca_memory' => $this->t('Runtime in-memory cache'),
      'eca_chained' => $this->t('Chained cache (in-memory plus shared)'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheKey(): ?string {
    $key = parent::getCacheKey();
    if ($key !== NULL) {
      // @noinspection StrStartsWithCanBeUsedInspection
      if (mb_strpos($key, 'eca_cache:') !== 0) {
        $key = 'eca_cache:' . $key;
      }
      return $key;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags(): array {
    $tags = parent::getCacheTags();
    foreach ($tags as &$tag) {
      // @noinspection StrStartsWithCanBeUsedInspection
      if (mb_strpos($tag, 'eca_cache:') !== 0) {
        $tag = 'eca_cache:' . $tag;
      }
    }
    return $tags;
  }

}
