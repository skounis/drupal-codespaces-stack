<?php

namespace Drupal\ai_agents_form_integration\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for batch redirect.
 */
class BatchRedirect extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    protected CacheBackendInterface $cache,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default')
    );
  }

  /**
   * Redirect to the batch page.
   */
  public function startRedirect() {
    // Get the webform_id, image, and text from cache.
    $info = $this->cache->get('ai_webform')->data;

    // Set 404 if the cache is not there.
    if (empty($info['webform_id'])) {
      throw new NotFoundHttpException();
    }
    // Batch API invoke.
    \batch_set([
      'operations' => [
        [
          '\Drupal\ai_agents_form_integration\Batch\WebformBatchJob::createDescription', [
            $info['file'],
            $info['text'],
            $info['open'],
            $info['description'],
          ],
        ],
        [
          '\Drupal\ai_agents_form_integration\\Batch\WebformBatchJob::runAgent',
          [
            $info['webform_id'],
          ],
        ],
      ],
      'finished' => '\Drupal\ai_agents_form_integration\\Batch\WebformBatchJob::batchFinished',
      'title' => $this->t('AI Webform Creation'),
    ]);
    return \batch_process();
  }

}
