<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\eca\Attributes\Token;
use Drupal\eca\EcaEvents;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\Event\TokenReceiverInterface;

/**
 * Prepares and cleans up the Token service when executing ECA logic.
 */
class EcaExecutionTokenSubscriber extends EcaExecutionSubscriberBase {

  /**
   * Subscriber method before initial execution.
   *
   * @param \Drupal\eca\Event\BeforeInitialExecutionEvent $event
   *   The according event.
   */
  #[Token(
    name: 'VARIOUS',
    description: 'All tokens forwarded by the dispatcher.',
    classes: [TokenReceiverInterface::class],
  )]
  public function onBeforeInitialExecution(BeforeInitialExecutionEvent $event): void {
    // Determine explicitly defined tokens to be forwarded.
    $forwardTokens = [];
    $triggeredEvent = $event->getEvent();
    if ($triggeredEvent instanceof TokenReceiverInterface) {
      foreach ($triggeredEvent->getTokenNamesToReceive() as $key) {
        if ($this->tokenService->hasTokenData($key)) {
          $forwardTokens[$key] = $this->tokenService->getTokenData($key);
        }
      }
    }

    // The following block resets the data state of the Token services, with an
    // exception for explicitly defined Tokens to be forwarded. This reset step
    // is necessary, so that variables are only available within their scope.
    $token_data = $this->tokenService->getTokenData();
    $event->setPrestate('token_data', $token_data);
    $this->tokenService->clearTokenData();
    foreach ($forwardTokens as $key => $value) {
      $this->tokenService->addTokenData($key, $value);
    }
  }

  /**
   * Subscriber method after initial execution.
   *
   * @param \Drupal\eca\Event\AfterInitialExecutionEvent $event
   *   The according event.
   */
  public function onAfterInitialExecution(AfterInitialExecutionEvent $event): void {
    // Determine explicitly defined tokens to be received back.
    $receiveTokens = [];
    $triggeredEvent = $event->getEvent();
    if ($triggeredEvent instanceof TokenReceiverInterface) {
      foreach ($triggeredEvent->getTokenNamesToReceive() as $key) {
        if ($this->tokenService->hasTokenData($key)) {
          $receiveTokens[$key] = $this->tokenService->getTokenData($key);
        }
      }
    }

    // Clear the Token data once more, and restore the state of Token data
    // for the wrapping logic (if any). Doing so prevents locally scoped Tokens
    // from unintentionally breaking out.
    $this->tokenService->clearTokenData();
    $token_data = $event->getPrestate('token_data') ?? [];
    foreach ($token_data as $key => $data) {
      $this->tokenService->addTokenData($key, $data);
    }
    foreach ($receiveTokens as $key => $value) {
      $this->tokenService->addTokenData($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[EcaEvents::BEFORE_INITIAL_EXECUTION][] = [
      'onBeforeInitialExecution',
      1000,
    ];
    $events[EcaEvents::AFTER_INITIAL_EXECUTION][] = [
      'onAfterInitialExecution',
      -1000,
    ];
    return $events;
  }

}
