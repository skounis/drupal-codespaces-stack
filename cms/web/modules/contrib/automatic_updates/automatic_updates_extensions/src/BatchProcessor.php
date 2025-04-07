<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * A batch processor for updates.
 *
 * @internal
 *   This class is an internal part of the module's update handling and
 *   should not be used by external code.
 */
final class BatchProcessor {

  /**
   * The session key under which the stage ID is stored.
   *
   * @var string
   */
  public const STAGE_ID_SESSION_KEY = '_automatic_updates_extensions_stage_id';

  /**
   * Gets the update stage service.
   *
   * @return \Drupal\automatic_updates_extensions\ExtensionUpdateStage
   *   The update stage service.
   */
  private static function getStage(): ExtensionUpdateStage {
    return \Drupal::service(ExtensionUpdateStage::class);
  }

  /**
   * Records messages from a throwable, then re-throws it.
   *
   * @param \Throwable $error
   *   The caught exception.
   * @param array $context
   *   The current context of the batch job.
   *
   * @throws \Throwable
   *   The caught exception, which will always be re-thrown once its messages
   *   have been recorded.
   */
  private static function handleException(\Throwable $error, array &$context): void {
    $context['results']['errors'][] = $error->getMessage();
    throw $error;
  }

  /**
   * Calls the update stage's begin() method.
   *
   * @param string[] $project_versions
   *   The project versions to be staged in the update, keyed by package name.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates_extensions\ExtensionUpdateStage::begin()
   */
  public static function begin(array $project_versions, array &$context): void {
    try {
      $stage_id = static::getStage()->begin($project_versions);
      \Drupal::service('session')->set(static::STAGE_ID_SESSION_KEY, $stage_id);
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the update stage's stage() method.
   *
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\UpdateStage::stage()
   */
  public static function stage(array &$context): void {
    $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
    try {
      static::getStage()->claim($stage_id)->stage();
    }
    catch (\Throwable $e) {
      // If the stage was not already destroyed because of this exception
      // destroy it.
      if (!static::getStage()->isAvailable()) {
        static::clean($stage_id, $context);
      }
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the update stage's apply() method.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates_extensions\ExtensionUpdateStage::apply()
   */
  public static function commit(string $stage_id, array &$context): void {
    try {
      static::getStage()->claim($stage_id)->apply();
      // The batch system does not allow any single request to run for longer
      // than a second, so this will force the next operation to be done in a
      // new request. This helps keep the running code in as consistent a state
      // as possible.
      // @see \Drupal\package_manager\Stage::apply()
      // @see \Drupal\package_manager\Stage::postApply()
      // @todo See if there's a better way to ensure the post-apply tasks run
      //   in a new request in https://www.drupal.org/i/3293150.
      sleep(1);
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the update stage's postApply() method.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates\UpdateStage::postApply()
   */
  public static function postApply(string $stage_id, array &$context): void {
    try {
      static::getStage()->claim($stage_id)->postApply();
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Calls the update stage's destroy() method.
   *
   * @param string $stage_id
   *   The stage ID.
   * @param array $context
   *   The current context of the batch job.
   *
   * @see \Drupal\automatic_updates_extensions\ExtensionUpdateStage::destroy()
   */
  public static function clean(string $stage_id, array &$context): void {
    try {
      static::getStage()->claim($stage_id)->destroy();
    }
    catch (\Throwable $e) {
      static::handleException($e, $context);
    }
  }

  /**
   * Finishes the stage batch job.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results.
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finishStage(bool $success, array $results, array $operations): ?RedirectResponse {
    if ($success) {
      $stage_id = \Drupal::service('session')->get(static::STAGE_ID_SESSION_KEY);
      $url = Url::fromRoute('automatic_updates_extension.confirmation_page', [
        'stage_id' => $stage_id,
      ]);
      return new RedirectResponse($url->setAbsolute()->toString());
    }
    static::handleBatchError($results);
    return NULL;
  }

  /**
   * Finishes the commit batch job.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results.
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finishCommit(bool $success, array $results, array $operations): ?RedirectResponse {
    \Drupal::service('session')->remove(static::STAGE_ID_SESSION_KEY);

    if ($success) {
      $url = Url::fromRoute('automatic_updates.finish')
        ->setAbsolute()
        ->toString();
      return new RedirectResponse($url);
    }
    static::handleBatchError($results);
    return NULL;
  }

  /**
   * Handles a batch job that finished with errors.
   *
   * @param array $results
   *   The batch results.
   */
  private static function handleBatchError(array $results): void {
    if (isset($results['errors'])) {
      foreach ($results['errors'] as $error) {
        \Drupal::messenger()->addError($error);
      }
    }
    else {
      \Drupal::messenger()->addError("Update error");
    }
  }

}
