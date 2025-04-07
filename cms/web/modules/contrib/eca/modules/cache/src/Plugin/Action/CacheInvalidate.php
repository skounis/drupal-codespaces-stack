<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;

/**
 * Abstract action to invalidate cache.
 */
abstract class CacheInvalidate extends CacheActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'tags' => '',
    ]
    + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cache tags'),
      '#description' => $this->t('Optionally define cache tags for fine-granular cache invalidation. Separate multiple tags with commas. More information about cache tags can be found in the <a href=":url" target="_blank" rel="nofollow noreferrer">documentation</a>. When empty, then the whole cache is being invalidated.', [
        ':url' => 'https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags',
      ]),
      '#default_value' => $this->configuration['tags'],
      '#weight' => -30,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['tags'] = $form_state->getValue('tags');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($cache = $this->getCacheBackend())) {
      return;
    }

    $tags = $this->getCacheTags();

    if (empty($tags)) {
      $cache->invalidateAll();
    }
    else {
      Cache::invalidateTags($tags);
    }
  }

}
