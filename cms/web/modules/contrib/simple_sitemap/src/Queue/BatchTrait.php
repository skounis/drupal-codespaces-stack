<?php

namespace Drupal\simple_sitemap\Queue;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a helper with batch callbacks.
 */
trait BatchTrait {

  use StringTranslationTrait;

  /**
   * An associative array defining the batch.
   *
   * @var array
   */
  protected $batch;

  /**
   * Message displayed if an error occurred while processing the batch.
   *
   * @var string
   */
  protected static $batchErrorMessage = 'The generation failed to finish. It can be continued manually on the module\'s settings page, or via drush.';

  /**
   * Adds a new batch.
   *
   * @param string $from
   *   The source of generation.
   * @param array|null $variants
   *   An array of variants.
   *
   * @return bool
   *   TRUE if batch was added and FALSE otherwise.
   */
  public function batchGenerate(string $from = self::GENERATE_TYPE_FORM, ?array $variants = NULL): bool {
    $this->batch = (new BatchBuilder())
      ->setTitle($this->t('Generating XML sitemaps'))
      ->setInitMessage($this->t('Initializing...'))
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      ->setErrorMessage($this->t(self::$batchErrorMessage))
      ->setProgressMessage($this->t('Processing items from the queue.<br>Each sitemap gets published after all of its items have been processed.'))
      ->addOperation([static::class, 'doBatchGenerate'])
      ->setFinishCallback([static::class, 'finishGeneration'])
      ->toArray();

    switch ($from) {

      case self::GENERATE_TYPE_FORM:
        // Start batch process.
        batch_set($this->batch);
        return TRUE;

      case self::GENERATE_TYPE_DRUSH:
        // Start drush batch process.
        batch_set($this->batch);

        // See https://www.drupal.org/node/638712
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;

        drush_backend_batch_process();
        return TRUE;
    }

    return FALSE;
  }

  /**
   * Processes the batch item.
   *
   * @param mixed $context
   *   The batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @todo Variants into generate().
   */
  public static function doBatchGenerate(&$context): void {

    /** @var \Drupal\simple_sitemap\Queue\QueueWorker $queue_worker */
    $queue_worker = \Drupal::service('simple_sitemap.queue_worker');

    $queue_worker->generate();
    $processed_element_count = $queue_worker->getProcessedElementCount();
    $original_element_count = $queue_worker->getInitialElementCount();

    $context['message'] = t('@indexed out of @total total queue items have been processed.', [
      '@indexed' => $processed_element_count,
      '@total' => $original_element_count,
    ]);
    $context['finished'] = $original_element_count > 0 ? ($processed_element_count / $original_element_count) : 1;
  }

  /**
   * Callback function called by the batch API when all operations are finished.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   *
   * @return bool
   *   Indicates whether the batch process was successful.
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public static function finishGeneration(bool $success, array $results, array $operations): bool {
    /** @var \Drupal\simple_sitemap\Logger $logger */
    $logger = \Drupal::service('simple_sitemap.logger');
    if ($success) {
      $logger
        ->m('The XML sitemaps have been regenerated.')
        ->log('info');
    }
    else {
      $logger
        ->m(self::$batchErrorMessage)
        ->display('error', 'administer sitemap settings')
        ->log('error');
    }

    return $success;
  }

}
