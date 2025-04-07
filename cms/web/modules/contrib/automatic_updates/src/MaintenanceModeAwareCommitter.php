<?php

declare(strict_types=1);

namespace Drupal\automatic_updates;

use Drupal\Core\State\StateInterface;
use Drupal\package_manager\Event\PostApplyEvent;
use PhpTuf\ComposerStager\API\Core\CommitterInterface;
use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;
use PhpTuf\ComposerStager\API\Exception\PreconditionException;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Path\Value\PathListInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the site into maintenance mode while staged changes are applied.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class MaintenanceModeAwareCommitter implements CommitterInterface, EventSubscriberInterface {

  /**
   * The state key which holds the original status of maintenance mode.
   *
   * @var string
   */
  private const STATE_KEY = 'automatic_updates.maintenance_mode';

  public function __construct(
    private readonly CommitterInterface $decorated,
    private readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostApplyEvent::class => ['restore', PHP_INT_MAX],
    ];
  }

  /**
   * Restores the original maintenance mode status after the update is applied.
   *
   * @param \Drupal\package_manager\Event\PostApplyEvent $event
   *   The event being handled.
   */
  public function restore(PostApplyEvent $event): void {
    if ($event->stage->getType() === 'automatic_updates:unattended') {
      $this->doRestore();
    }
  }

  /**
   * Restores the original maintenance mode status.
   */
  private function doRestore(): void {
    $this->state->set('system.maintenance_mode', $this->state->get(static::STATE_KEY));
  }

  /**
   * {@inheritdoc}
   */
  public function commit(PathInterface $stagingDir, PathInterface $activeDir, ?PathListInterface $exclusions = NULL, ?OutputCallbackInterface $callback = NULL, ?int $timeout = ProcessInterface::DEFAULT_TIMEOUT,): void {
    $this->state->set(static::STATE_KEY, $this->state->get('system.maintenance_mode', FALSE));
    $this->state->set('system.maintenance_mode', TRUE);

    try {
      $this->decorated->commit($stagingDir, $activeDir, $exclusions, $callback, $timeout);
    }
    catch (PreconditionException | InvalidArgumentException $e) {
      $this->doRestore();

      // Re-throw the exception, wrapped by another instance of itself.
      $message = $e->getTranslatableMessage();
      $code = $e->getCode();
      // PreconditionException takes the failed precondition as its first
      // argument.
      if ($e instanceof PreconditionException) {
        throw new PreconditionException($e->getPrecondition(), $message, $code, $e);
      }
      $class = get_class($e);
      throw new $class($message, $code, $e);
    }
  }

}
