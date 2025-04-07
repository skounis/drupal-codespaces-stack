<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for generating the status checkers' output for hook_requirements().
 *
 * @see automatic_updates_requirements()
 *
 * @internal
 *   This class implements logic to output the messages from status checkers
 *   on the status report page. It should not be called directly.
 */
final class StatusCheckRequirements implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use ValidationResultDisplayTrait;

  public function __construct(
    private readonly StatusChecker $statusChecker,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(StatusChecker::class),
      $container->get('date.formatter'),
      $container->get('config.factory'),
    );
  }

  /**
   * Returns the method used to run unattended updates.
   *
   * @return string
   *   The method used to run unattended updates. Will be either 'console' or
   *   'web'.
   */
  private function getMethod(): string {
    return $this->configFactory->get('automatic_updates.settings')
      ->get('unattended.method');
  }

  /**
   * Gets requirements arrays as specified in hook_requirements().
   *
   * @return mixed[]
   *   Requirements arrays as specified by hook_requirements().
   */
  public function getRequirements(): array {
    $requirements = [];

    $results = $this->statusChecker->getResults();
    // If unattended updates are run on the terminal, we don't want to do the
    // status check right now, since running them over the web may yield
    // inaccurate or irrelevant results. The console command runs status checks,
    // so if there are no results, we can assume it has not been run in a while,
    // and raise an error about that.
    if (is_null($results) && $this->getMethod() === 'console') {
      $requirements['automatic_updates_status_check_console_command_not_run'] = [
        'title' => $this->t('Update readiness checks'),
        'severity' => SystemManager::REQUIREMENT_ERROR,
        // @todo Link to the documentation on how to set up unattended updates
        //   via the terminal in https://drupal.org/i/3362695.
        'value' => $this->t('Unattended updates are configured to run via the console, but do not appear to have run recently.'),
      ];
      return $requirements;
    }

    $results ??= $this->statusChecker->run()->getResults();
    if (empty($results)) {
      $requirements['automatic_updates_status_check'] = [
        'title' => $this->t('Update readiness checks'),
        'severity' => SystemManager::REQUIREMENT_OK,
        // @todo Link "automatic updates" to documentation in
        //   https://www.drupal.org/node/3168405.
        'value' => $this->t('Your site is ready for automatic updates.'),
      ];
      $run_link = $this->createRunLink();
      if ($run_link) {
        $requirements['automatic_updates_status_check']['description'] = $run_link;
      }
    }
    else {
      foreach ([SystemManager::REQUIREMENT_WARNING, SystemManager::REQUIREMENT_ERROR] as $severity) {
        if ($requirement = $this->createRequirementForSeverity($severity)) {
          $requirements["automatic_updates_status_$severity"] = $requirement;
        }
      }
    }
    return $requirements;
  }

  /**
   * Creates a requirement for checker results of a specific severity.
   *
   * @param int $severity
   *   The severity for requirement. Should be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @return mixed[]|null
   *   Requirements array as specified by hook_requirements(), or NULL
   *   if no requirements can be determined.
   */
  private function createRequirementForSeverity(int $severity): ?array {
    $severity_messages = [];
    $results = $this->statusChecker->getResults($severity);
    if (!$results) {
      return NULL;
    }
    foreach ($results as $result) {
      $checker_messages = $result->messages;
      $summary = $result->summary;
      if (empty($summary)) {
        $severity_messages[] = ['#markup' => array_pop($checker_messages)];
      }
      else {
        $severity_messages[] = [
          '#type' => 'details',
          '#title' => $summary,
          '#open' => FALSE,
          'messages' => [
            '#theme' => 'item_list',
            '#items' => $checker_messages,
          ],
        ];
      }
    }
    $requirement = [
      'title' => $this->t('Update readiness checks'),
      'severity' => $severity,
      'value' => $this->getFailureMessageForSeverity($severity),
      'description' => [
        'messages' => [
          '#theme' => 'item_list',
          '#items' => $severity_messages,
        ],
      ],
    ];
    if ($run_link = $this->createRunLink()) {
      $requirement['description']['run_link'] = [
        '#type' => 'container',
        '#markup' => $run_link,
      ];
    }
    return $requirement;
  }

  /**
   * Creates a link to run the status checks.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   A link, if the user has access to run the status checks, otherwise
   *   NULL.
   */
  private function createRunLink(): ?TranslatableMarkup {
    // Only show this link if unattended updates are being run over the web.
    if ($this->getMethod() !== 'web') {
      return NULL;
    }

    $status_check_url = Url::fromRoute('automatic_updates.status_check');
    if ($status_check_url->access()) {
      return $this->t(
        '<a href=":link">Rerun readiness checks</a> now.',
        [':link' => $status_check_url->toString()]
      );
    }
    return NULL;
  }

}
