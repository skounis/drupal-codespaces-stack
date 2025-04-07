<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Controller;

use Drupal\automatic_updates\BatchProcessor;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\package_manager\Validator\PendingUpdatesValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to handle various stages of an automatic update.
 *
 * @internal
 *   Controller classes are internal.
 */
final class UpdateController extends ControllerBase {

  public function __construct(
    private readonly PendingUpdatesValidator $pendingUpdatesValidator,
    private readonly RouteMatchInterface $routeMatch,
    private readonly StatusChecker $statusChecker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(PendingUpdatesValidator::class),
      $container->get('current_route_match'),
      $container->get(StatusChecker::class),
    );
  }

  /**
   * Redirects after staged changes are applied to the active directory.
   *
   * If there are any pending update hooks or post-updates, the user is sent to
   * update.php to run those. Otherwise, they are redirected to the status
   * report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the appropriate destination.
   */
  public function onFinish(Request $request): RedirectResponse {
    if ($this->pendingUpdatesValidator->updatesExist()) {
      // If there are pending database updates then the status checks will be
      // run after the database updates are completed.
      // @see \Drupal\automatic_updates\BatchProcessor::dbUpdateBatchFinished
      $message = $this->t('Apply database updates to complete the update process.');
      $url = Url::fromRoute('system.db_update');
    }
    else {
      $this->statusChecker->run();
      $message = $this->t('Update complete!');
      $url = Url::fromRoute('update.status');
      // Now that the update is done, we can put the site back online if it was
      // previously not in maintenance mode.
      if (!$request->getSession()->remove(BatchProcessor::MAINTENANCE_MODE_SESSION_KEY)) {
        $this->state()->set('system.maintenance_mode', FALSE);
        // @todo Remove once the core bug that shows the maintenance mode
        //   message after the site is out of maintenance mode is fixed in
        //   https://www.drupal.org/i/3279246.
        $status_messages = $this->messenger()->messagesByType(MessengerInterface::TYPE_STATUS);
        $status_messages = array_filter($status_messages, function (string $message) {
          return !str_starts_with($message, (string) $this->t('Operating in maintenance mode.'));
        });
        $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
        foreach ($status_messages as $status_message) {
          $this->messenger()->addStatus($status_message);
        }
      }
    }
    $this->messenger()->addStatus($message);
    return new RedirectResponse($url->setAbsolute()->toString());
  }

}
