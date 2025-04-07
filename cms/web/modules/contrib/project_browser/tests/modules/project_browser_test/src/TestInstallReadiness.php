<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\system\SystemManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Simulates status check results for Project Browser's installer.
 */
final class TestInstallReadiness implements EventSubscriberInterface {

  public function __construct(private readonly StateInterface $state) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StatusCheckEvent::class => 'onStatusCheck',
    ];
  }

  /**
   * Sets simulated errors or warnings during a Project Browser status check.
   *
   * @param \Drupal\package_manager\Event\StatusCheckEvent $event
   *   The event object.
   */
  public function onStatusCheck(StatusCheckEvent $event): void {
    // We don't care about anything except Project Browser's installer.
    if ($event->stage->getType() !== 'project_browser.installer') {
      return;
    }

    $severity = $this->state->get('project_browser_test.simulated_result_severity');

    if ($severity === SystemManager::REQUIREMENT_ERROR) {
      $event->addError([
        new TranslatableMarkup('Simulate an error message for the project browser.'),
      ]);
    }
    elseif ($severity === SystemManager::REQUIREMENT_WARNING) {
      $event->addWarning([
        new TranslatableMarkup('Simulate a warning message for the project browser.'),
      ]);
    }
  }

}
