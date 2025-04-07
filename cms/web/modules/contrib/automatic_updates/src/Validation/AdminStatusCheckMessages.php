<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validation;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\package_manager\ValidationResult;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for displaying status check results on admin pages.
 *
 * @internal
 *   This class implements logic to output the messages from status checkers
 *   on admin pages. It should not be called directly.
 */
final class AdminStatusCheckMessages implements ContainerInjectionInterface {

  use MessengerTrait;
  use StringTranslationTrait;
  use RedirectDestinationTrait;
  use ValidationResultDisplayTrait;

  public function __construct(
    private readonly StatusChecker $statusChecker,
    private readonly AdminContext $adminContext,
    private readonly AccountProxyInterface $currentUser,
    private readonly CurrentRouteMatch $currentRouteMatch,
    private readonly CronUpdateRunner $runner,
    private readonly RendererInterface $renderer,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(StatusChecker::class),
      $container->get('router.admin_context'),
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get(CronUpdateRunner::class),
      $container->get('renderer'),
      $container->get('config.factory')
    );
  }

  /**
   * Displays the checker results messages on admin pages.
   */
  public function displayAdminPageMessages(): void {
    if (!$this->displayResultsOnCurrentPage()) {
      return;
    }
    if ($this->statusChecker->getResults() === NULL) {
      $method = $this->configFactory->get('automatic_updates.settings')
        ->get('unattended.method');

      $checker_url = Url::fromRoute('automatic_updates.status_check')->setOption('query', $this->getDestinationArray());
      if ($method === 'web' && $checker_url->access()) {
        $this->messenger()->addError($this->t('Your site has not recently run an update readiness check. <a href=":url">Rerun readiness checks now.</a>', [
          ':url' => $checker_url->toString(),
        ]));
      }
      elseif ($method === 'console') {
        // @todo Link to the documentation on how to set up unattended updates
        //   via the terminal in https://drupal.org/i/3362695.
        $message = $this->t('Unattended updates are configured to run via the console, but not appear to have run recently.');
        $this->messenger()->addError($message);
      }
    }
    else {
      // Display errors, if there are any. If there aren't, then display
      // warnings, if there are any.
      if (!$this->displayResultsForSeverity(SystemManager::REQUIREMENT_ERROR)) {
        $this->displayResultsForSeverity(SystemManager::REQUIREMENT_WARNING);
      }
    }
  }

  /**
   * Determines whether the messages should be displayed on the current page.
   *
   * @return bool
   *   Whether the messages should be displayed on the current page.
   */
  private function displayResultsOnCurrentPage(): bool {
    // If updates will not run during cron then we don't need to show the
    // status checks on admin pages.
    if ($this->runner->getMode() === CronUpdateRunner::DISABLED) {
      return FALSE;
    }

    if ($this->adminContext->isAdminRoute() && $this->currentUser->hasPermission('administer site configuration')) {
      return $this->currentRouteMatch->getRouteObject()
        ?->getOption('_automatic_updates_status_messages') !== 'skip';
    }
    return FALSE;
  }

  /**
   * Displays the results for severity.
   *
   * @param int $severity
   *   The severity for the results to display. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @return bool
   *   Whether any results were displayed.
   */
  private function displayResultsForSeverity(int $severity): bool {
    $results = $this->statusChecker->getResults($severity);
    if (empty($results)) {
      return FALSE;
    }
    $this->displayResults($results, $this->messenger(), $this->renderer);
    return TRUE;
  }

  /**
   * Displays the result summary.
   */
  public function displayResultSummary(): void {
    if (!$this->currentUser->hasPermission('administer site configuration')) {
      return;
    }
    $results = $this->statusChecker->getResults();
    if (empty($results)) {
      return;
    }
    // First message: severity.
    $overall_severity = ValidationResult::getOverallSeverity($results);
    $message = $this->getFailureMessageForSeverity($overall_severity);
    $message_type = $overall_severity === SystemManager::REQUIREMENT_ERROR ? MessengerInterface::TYPE_ERROR : MessengerInterface::TYPE_WARNING;
    $this->messenger()->addMessage($message, $message_type);

    // Optional second message: more details (for users with sufficient
    // permissions).
    $status_report_url = Url::fromRoute('system.status');
    if ($status_report_url->access()) {
      $this->messenger()->addMessage(
      $this->t('<a href=":url">See status report for more details.</a>', [
        ':url' => $status_report_url->toString(),
      ]),
      $message_type,
      );
    }
  }

  /**
   * Adds a set of validation results to the messages.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   The validation results.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  private function displayResults(array $results, MessengerInterface $messenger, RendererInterface $renderer): void {
    $severity = ValidationResult::getOverallSeverity($results);

    if ($severity === SystemManager::REQUIREMENT_OK) {
      return;
    }

    // Display a single message for each validation result, even if it has
    // multiple messages. This is because, on regular admin pages, we merely
    // want to alert users that problems exist, but not burden them with the
    // details. They can get those on the status report and updater form.
    $format_result = function (ValidationResult $result): TranslatableMarkup|string {
      $messages = $result->messages;
      return $result->summary ?: reset($messages);
    };
    // Format the results as a single item list prefixed by a preamble message.
    $build = [
      '#theme' => 'item_list__automatic_updates_validation_results',
      '#prefix' => $this->getFailureMessageForSeverity($severity),
      '#items' => array_map($format_result, $results),
    ];
    $message = $renderer->renderRoot($build);

    if ($severity === SystemManager::REQUIREMENT_ERROR) {
      $messenger->addError($message);
    }
    else {
      $messenger->addWarning($message);
    }
  }

}
