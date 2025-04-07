<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\package_manager\ValidationResult;
use Drupal\system\SystemManager;

/**
 * Base class for update forms provided by Automatic Updates.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not extend this class.
 */
abstract class UpdateFormBase extends FormBase {

  use StatusCheckTrait;

  /**
   * Adds a set of validation results to the messages.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   The validation results.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  protected function displayResults(array $results, RendererInterface $renderer): void {
    $severity = ValidationResult::getOverallSeverity($results);

    if ($severity === SystemManager::REQUIREMENT_OK) {
      return;
    }

    // Format the results as a single item list prefixed by a preamble message
    // if necessary.
    $build = [
      '#theme' => 'item_list__automatic_updates_validation_results',
    ];
    if ($severity === SystemManager::REQUIREMENT_ERROR) {
      $build['#prefix'] = $this->t('Your site cannot be automatically updated until further action is performed.');
    }
    foreach ($results as $result) {
      $messages = $result->messages;

      // If there's a summary, there's guaranteed to be at least one message,
      // so render the result as a nested list.
      $summary = $result->summary;
      if ($summary) {
        $build['#items'][] = [
          '#theme' => $build['#theme'],
          '#prefix' => $summary,
          '#items' => $messages,
        ];
      }
      else {
        $build['#items'][] = reset($messages);
      }
    }
    $message = $renderer->renderRoot($build);

    if ($severity === SystemManager::REQUIREMENT_ERROR) {
      $this->messenger()->addError($message);
    }
    else {
      $this->messenger()->addWarning($message);
    }
  }

}
