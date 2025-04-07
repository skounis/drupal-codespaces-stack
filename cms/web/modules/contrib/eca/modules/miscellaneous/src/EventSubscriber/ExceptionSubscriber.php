<?php

namespace Drupal\eca_misc\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\eca_misc\Event\EcaExceptionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for all sorts of routing errors.
 */
class ExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs the error subscriber.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['html'];
  }

  /**
   * Handles all 4xx errors.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The Event to process.
   */
  public function on4xx(ExceptionEvent $event): void {
    $this->errorHandler($event);
  }

  /**
   * Handles all 5xx errors.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The Event to process.
   */
  public function on5xx(ExceptionEvent $event): void {
    $this->errorHandler($event);
  }

  /**
   * Helper function to handle all errors.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The Event to process.
   */
  protected function errorHandler(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if (method_exists($exception, 'getStatusCode')) {
      $statusCode = $exception->getStatusCode();
      $exceptionEvent = new EcaExceptionEvent($statusCode);
      try {
        $this->eventDispatcher->dispatch($exceptionEvent, EcaExceptionEvent::EVENT_NAME);
      }
      catch (\Exception $ex) {
        $event->setThrowable($ex);
      }

      $response = NULL;
      foreach ($this->eventDispatcher->getListeners(KernelEvents::RESPONSE) as $listener) {
        if ($listener instanceof \Closure) {
          try {
            $reflectionClosure = new \ReflectionFunction($listener);
            foreach ($reflectionClosure->getStaticVariables() as $staticVariable) {
              if ($staticVariable instanceof RedirectResponse) {
                $response = $staticVariable;
                break 2;
              }
            }
          }
          catch (\ReflectionException) {
            // Nothing we can do about this.
          }
        }
      }

      if ($response !== NULL) {
        $event->setResponse($response);
      }
    }
  }

}
