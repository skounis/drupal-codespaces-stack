<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Validator;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\package_manager\Event\PreCreateEvent;
use Drupal\package_manager\Event\PreOperationStageEvent;
use Drupal\package_manager\Event\StatusCheckEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Disallows unattended background updates on Windows systems.
 */
final class WindowsValidator implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The value of the PHP_OS constant.
   *
   * @var string
   */
  private static $os = PHP_OS;

  public function __construct(
    private readonly CronUpdateRunner $cronRunner,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StatusCheckEvent::class => 'validate',
      PreCreateEvent::class => 'validate',
    ];
  }

  /**
   * Disallows unattended updates if running on Windows.
   *
   * @param \Drupal\package_manager\Event\PreOperationStageEvent $event
   *   The event being handled.
   */
  public function validate(PreOperationStageEvent $event): void {
    // If we're not on Windows, there's nothing for us to validate.
    if (!str_starts_with(strtoupper(static::$os), 'WIN')) {
      return;
    }

    $method = $this->configFactory->get('automatic_updates.settings')
      ->get('unattended.method');

    $stage = $event->stage;
    if ($stage->getType() === 'automatic_updates:unattended' && $this->cronRunner->getMode() !== CronUpdateRunner::DISABLED && $method === 'web') {
      $message = $this->t('Unattended updates are not supported on Windows.');

      $form_url = Url::fromRoute('update.report_update');
      if ($form_url->access()) {
        $message = $this->t('@message Use <a href=":form-url">the update form</a> to update Drupal core.', [
          '@message' => $message,
          ':form-url' => $form_url->toString(),
        ]);
      }
      $event->addError([$message]);
    }
  }

}
