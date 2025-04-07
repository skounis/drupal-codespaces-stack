<?php

namespace Drupal\simple_sitemap_engines\Submitter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Site\Settings;
use Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine;

/**
 * Sitemap submitting service.
 */
class IndexNowSubmitter extends SubmitterBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The IndexNow key.
   *
   * @var string|false
   */
  protected $key;

  /**
   * The search IndexNow capable search engine entity.
   *
   * @var \Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine|false
   */
  protected $engine;

  /**
   * {@inheritdoc}
   */
  protected function onSuccess(): void {
    $this->logger->m('Entity <em>@entity</em> was submitted to @engine.', [
      '@entity' => $this->entity->getEntityTypeId() . ':' . $this->entity->id(),
      '@engine' => $this->engine->label(),
    ])->log();

    $this->state->set(
      'simple_sitemap_engines.index_now.last', [
        'time' => $this->time->getRequestTime(),
        'engine' => $this->engine->id(),
        'engine_label' => $this->engine->label(),
        'entity' => $this->entity->getEntityTypeId() . ':' . $this->entity->id(),
        'entity_label' => $this->entity->label() ?: '',
      ]
    );
  }

  /**
   * Gets the IndexNow key.
   *
   * @return string|false
   *   The IndexNow key or FALSE if the key is not set.
   */
  public function getKey() {
    if ($this->key === NULL
      && empty($this->key = (string) Settings::get('simple_sitemap_engines.index_now.key'))
      && empty($this->key = (string) $this->state->get('simple_sitemap_engines.index_now.key'))
    ) {
      $this->key = FALSE;
    }

    return $this->key;
  }

  /**
   * Gets search engine entity.
   *
   * @return \Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine|false
   *   The search engine entity.
   */
  protected function getEngine() {
    if ($this->engine === NULL) {
      if ($id = $this->config->get('simple_sitemap_engines.settings')->get('index_now_preferred_engine')) {
        $engine = SimpleSitemapEngine::load($id);
        $this->engine = $engine && $engine->hasIndexNow() ? $engine : FALSE;
      }
      elseif ($engine = SimpleSitemapEngine::loadRandomIndexNowEngine()) {
        $this->engine = $engine;
      }
      else {
        $this->engine = FALSE;
      }
    }

    return $this->engine;
  }

  /**
   * Submit entity URL if it is set to be submitted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to submit.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submitIfSubmittable(EntityInterface $entity) {
    if ($this->config->get('simple_sitemap_engines.settings')->get('index_now_enabled')) {
      // Do not act on syncing operations (migration/import/...)
      if ($entity instanceof SynchronizableInterface && $entity->isSyncing()) {
        return;
      }

      // Entity was saved outside its entity form - indexing depending
      // on module and entity inclusion settings.
      if (!isset($entity->_simple_sitemap_index_now)) {
        if ($this->config->get('simple_sitemap_engines.settings')->get('index_now_on_entity_save')
          && $this->config->get("simple_sitemap_engines.bundle_settings.{$entity->getEntityTypeId()}.{$entity->bundle()}")->get('index_now')) {
          $this->submit($entity);
        }
      }

      // Form submission occurred, so we are indexing the entity depending
      // on the form settings.
      else {
        if ($entity->_simple_sitemap_index_now) {
          $this->submit($entity);
        }
        unset($entity->_simple_sitemap_index_now);
      }
    }
  }

  /**
   * Submit entity URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to submit.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submit(EntityInterface $entity): void {
    $this->entity = $entity;

    if (!$this->getEngine() || (!$this->getKey())) {
      return;
    }

    $base_url = $this->config->get('simple_sitemap.settings')->get('base_url');
    $url_options = $base_url ? ['base_url' => $base_url] : [];
    $this->request(
      str_replace(
        ['[url]', '[key]'],
        // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
        [urlencode($entity->toUrl('canonical', $url_options)->setAbsolute()->toString()), $this->getKey()],
        $this->getEngine()->index_now_url
      )
    );
  }

}
