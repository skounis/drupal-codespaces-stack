<?php

namespace Drupal\project_browser\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\project_browser\ComposerInstaller\Installer;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\project_browser\InstallState;
use Drupal\system\SystemManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a controller to install projects via UI.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class InstallerController extends ControllerBase {

  use StatusCheckTrait;

  /**
   * The endpoint successfully returned the expected data.
   *
   * @var int
   */
  protected const STAGE_STATUS_OK = 0;

  public function __construct(
    private readonly Installer $installer,
    private readonly EnabledSourceHandler $enabledSourceHandler,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly InstallState $installState,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $installer = $container->get(Installer::class);
    assert($installer instanceof Installer);

    return new static(
      $installer,
      $container->get(EnabledSourceHandler::class),
      $container->get(TimeInterface::class),
      $container->get('logger.channel.project_browser'),
      $container->get(InstallState::class),
      $container->get(EventDispatcherInterface::class),
    );
  }

  /**
   * Checks if UI install is enabled on the site.
   */
  public function access(): AccessResult {
    $ui_install = $this->config('project_browser.admin_settings')->get('allow_ui_install');
    return AccessResult::allowedIf((bool) $ui_install);
  }

  /**
   * Resets progress and destroys the stage.
   */
  private function cancelRequire(): void {
    $this->installState->deleteAll();
    // Checking the for the presence of a lock in the package manager stage is
    // necessary as this method can be called during create(), which includes
    // both the PreCreate and PostCreate events. If an exception is caught
    // during PreCreate, there's no stage to destroy and an exception would be
    // raised. So, we check for the presence of a stage before calling
    // destroy().
    if (!$this->installer->isAvailable() && $this->installer->lockCameFromProjectBrowserInstaller()) {
      // The risks of forcing a destroy with TRUE are understood, which is why
      // we first check if the lock originated from Project Browser. This
      // function is called if an exception is thrown during an install. This
      // can occur during a phase where the stage might not be claimable, so we
      // force-destroy with the TRUE parameter, knowing that the checks above
      // will prevent destroying an Automatic Updates stage or a stage that is
      // in the process of applying.
      $this->installer->destroy(TRUE);
    }
  }

  /**
   * Provides a JSON response for a given error.
   *
   * @param \Throwable $e
   *   The error that occurred.
   * @param string $phase
   *   The phase the error occurred in.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Provides an error message to be displayed by the Project Browser UI.
   */
  private function errorResponse(\Throwable $e, string $phase = ''): JsonResponse {
    $exception_type_short = (new \ReflectionClass($e))->getShortName();
    $exception_message = $e->getMessage();
    $response_body = ['message' => "$exception_type_short: $exception_message"];
    $this->logger->warning('@exception_type: @exception_message. @trace ', [
      '@exception_type' => get_class($e),
      '@exception_message' => $exception_message,
      '@trace' => $e->getTraceAsString(),
    ]);

    if (!empty($phase)) {
      $response_body['phase'] = $phase;
    }
    return new JsonResponse($response_body, 500);
  }

  /**
   * Provides a JSON response for a successful request.
   *
   * @param string $phase
   *   The phase the request was made in.
   * @param string|null $stage_id
   *   The stage ID of the installer within the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Provides information about the completed operation.
   */
  private function successResponse(string $phase, ?string $stage_id = NULL): JsonResponse {
    $response_body = [
      'phase' => $phase,
      'status' => self::STAGE_STATUS_OK,
    ];
    if (!empty($stage_id)) {
      $response_body['stage_id'] = $stage_id;
    }
    return new JsonResponse($response_body);
  }

  /**
   * Provides a JSON response for require requests while the stage is locked.
   *
   * @param string $message
   *   The message content of the response.
   * @param string $unlock_url
   *   An unlock url provided in instances where unlocking is safe.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Provides a message regarding the status of the staging lock.
   *
   *   If the stage is not in a phase where it is unsafe to unlock, a CSRF
   *   protected unlock URL is also provided.
   */
  private function lockedResponse(string $message, string $unlock_url = ''): JsonResponse {
    return new JsonResponse([
      'message' => $message,
      'unlock_url' => $unlock_url,
    ], 418);
  }

  /**
   * Unlocks and destroys the stage.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the main project browser page.
   */
  public function unlock(): JsonResponse|RedirectResponse {
    try {
      // It's possible the unlock url was provided before applying began, but
      // accessed after. This final check ensures a destroy is not attempted
      // during apply.
      if ($this->installer->isApplying()) {
        throw new StageException($this->installer, 'Another project is being added. Try again in a few minutes.');
      }

      // Adding the TRUE parameter to destroy is dangerous, but we provide it
      // here for a few reasons.
      // - This endpoint is only available if it's confirmed the stage lock was
      //   created by  Drupal\project_browser\ComposerInstaller\Installer.
      // - This endpoint is not available if the stage is applying.
      // - In the event of a flawed install, we want it to be possible for users
      //   to unlock the stage via the GUI, even if they're not the user that
      //   initiated the install.
      // - The unlock link is accompanied by information regarding when the
      //   stage was locked, and warns the user when the time is recent enough
      //   that they risk aborting a legitimate install.
      $this->installer->destroy(TRUE);
    }
    catch (\Exception $e) {
      return $this->errorResponse($e);
    }
    $this->installState->deleteAll();
    $this->messenger()->addStatus($this->t('Operation complete, you can add a new project again.'));

    $redirect = Url::fromUserInput($this->getRedirectDestination()->get())
      ->setAbsolute()
      ->toString();
    return new RedirectResponse($redirect);
  }

  /**
   * Gets the given URL with all placeholders replaced.
   *
   * @param \Drupal\Core\Url $url
   *   A URL which generates CSRF token placeholders.
   *
   * @return string
   *   The URL string, with all placeholders replaced.
   */
  private static function getUrlWithReplacedCsrfTokenPlaceholder(Url $url): string {
    $generated_url = $url->toString(TRUE);
    $url_with_csrf_token_placeholder = [
      '#plain_text' => $generated_url->getGeneratedUrl(),
    ];
    $generated_url->applyTo($url_with_csrf_token_placeholder);

    return (string) \Drupal::service('renderer')
      ->renderInIsolation($url_with_csrf_token_placeholder);
  }

  /**
   * Begins requiring by creating a stage.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Status message.
   */
  public function begin(Request $request): JsonResponse {
    $stage_available = $this->installer->isAvailable();
    if (!$stage_available) {
      // The sandbox is being used by something that isn't Project Browser (e.g.
      // Automatic Updates), so there's nothing we can do.
      if (!$this->installer->lockCameFromProjectBrowserInstaller()) {
        return $this->lockedResponse($this->t('The process for adding projects was locked by something else outside of Project Browser. Projects can be added again once the process is unlocked. Try again in a few minutes.'));
      }

      // If we got here, the sandbox is locked by us, so prepare a link that
      // allows the user to unlock it if possible.
      $unlock_url = self::getUrlWithReplacedCsrfTokenPlaceholder(
        Url::fromRoute('project_browser.install.unlock'),
      );
      $unlock_url .= '&destination=' . $request->query->get('redirect');

      // We had locked the sandbox, but never actually ended up requiring any
      // projects into it, so allow the user to unlock it right now.
      $updated_time = $this->installState->getFirstUpdatedTime();
      if (empty($updated_time)) {
        $message = $this->t('The process for adding projects is locked, but that lock has expired. Use [+ unlock link] to unlock the process and try to add the project again.');
        return $this->lockedResponse($message, $unlock_url);
      }

      // Figure out how long it's been since we locked the sandbox. In a test
      // environment, allow the current request time to be nudged around.
      $request_time = $this->time->getRequestTime();
      if (drupal_valid_test_ua()) {
        $request_time += $this->state()->get('InstallerController time offset', 0);
      }

      $seconds_since_updated = $request_time - $updated_time;
      $hours_since_updated = (int) floor($seconds_since_updated / 3600);
      $minutes_since_updated = (int) floor(($seconds_since_updated % 3600) / 60);

      if ($hours_since_updated) {
        $locked_since = $this->formatPlural($hours_since_updated, 'an hour ago', '@count hours ago');
      }
      else {
        $locked_since = $minutes_since_updated
          ? $this->formatPlural($minutes_since_updated, 'a minute ago', '@count minutes ago')
          : $this->t('less than a minute ago');
      }

      // If we're still applying changes, and it's been less than an hour, don't
      // offer the unlock link.
      if ($this->installer->isApplying() && $hours_since_updated === 0) {
        $message = $this->t('The process for adding the project was locked @since. It should not be unlocked while changes are being applied to the site.', [
          '@since' => $locked_since,
        ]);
        return $this->lockedResponse($message);
      }
      // If the sandbox has been locked for at least 7 minutes, offer the
      // unlock link.
      elseif ($minutes_since_updated > 7) {
        $message = $this->t('The process for adding the project was locked @since. Use [+ unlock link] to unlock the process.', [
          '@since' => $locked_since,
        ]);
      }
      // In all other cases, allow the user to unlock the sandbox, but ask them
      // to have some patience.
      else {
        $message = $this->t('The process for adding the project that was locked @since might still be in progress. Consider waiting a few more minutes before using [+unlock link].', [
          '@since' => $locked_since,
        ]);
      }
      return $this->lockedResponse($message, $unlock_url);
    }

    // Ensure the environment is ready to use Package Manager.
    ['errors' => $errors, 'warnings' => $warnings] = $this->validatePackageManager();
    if ($warnings) {
      $this->logger->warning(implode("\n", $warnings));
    }
    if ($errors) {
      $error_message = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
      return $this->errorResponse(new StageException($this->installer, $error_message));
    }

    try {
      $stage_id = $this->installer->create();
    }
    catch (\Exception $e) {
      $this->cancelRequire();
      return $this->errorResponse($e, 'create');
    }

    return $this->successResponse('create', $stage_id);
  }

  /**
   * Performs require operations on the stage.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $stage_id
   *   The stage ID of the installer within the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Status message.
   */
  public function require(Request $request, string $stage_id): JsonResponse {
    $package_names = [];
    foreach ($request->toArray() as $project_id) {
      $project = $this->enabledSourceHandler->getStoredProject($project_id);
      $this->installState->setState($project_id, 'requiring');
      $package_names[] = $project->packageName;
    }
    try {
      $this->installer->claim($stage_id)->require($package_names);
      return $this->successResponse('require', $stage_id);
    }
    catch (\Exception $e) {
      $this->cancelRequire();
      return $this->errorResponse($e, 'require');
    }
  }

  /**
   * Performs apply operations on the stage.
   *
   * @param string $stage_id
   *   The stage ID of the installer within the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Status message.
   */
  public function apply(string $stage_id): JsonResponse {
    foreach (array_keys($this->installState->toArray()) as $project_id) {
      $this->installState->setState($project_id, 'applying');
    }
    try {
      $this->installer->claim($stage_id)->apply();
    }
    catch (\Exception $e) {
      $this->cancelRequire();
      return $this->errorResponse($e, 'apply');
    }
    return $this->successResponse('apply', $stage_id);
  }

  /**
   * Performs post apply operations on the stage.
   *
   * @param string $stage_id
   *   The stage ID of the installer within the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Status message.
   */
  public function postApply(string $stage_id): JsonResponse {
    try {
      $this->installer->claim($stage_id)->postApply();
    }
    catch (\Exception $e) {
      return $this->errorResponse($e, 'post apply');
    }
    return $this->successResponse('post apply', $stage_id);
  }

  /**
   * Performs destroy operations on the stage.
   *
   * @param string $stage_id
   *   The stage ID of the installer within the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Status message.
   */
  public function destroy(string $stage_id): JsonResponse {
    try {
      $this->installer->claim($stage_id)->destroy();
    }
    catch (\Exception $e) {
      return $this->errorResponse($e, 'destroy');
    }
    return new JsonResponse([
      'phase' => 'destroy',
      'status' => self::STAGE_STATUS_OK,
      'stage_id' => $stage_id,
    ]);
  }

  /**
   * Checks if the environment meets Package Manager install requirements.
   *
   * @return array[]
   *   An array with two sub-elements:
   *   - errors: The validation messages with an "error" severity.
   *   - warnings: All other validation messages, which are probably warnings.
   */
  private function validatePackageManager(): array {
    $results = [
      'errors' => [],
      'warnings' => [],
    ];
    foreach ($this->runStatusCheck($this->installer, $this->eventDispatcher) as $result) {
      $group = $result->severity === SystemManager::REQUIREMENT_ERROR
        ? 'errors'
        : 'warnings';

      if ($result->summary) {
        $results[$group][] = $result->summary;
      }
      $results[$group] = array_merge($results[$group], $result->messages);
    }
    return $results;
  }

}
