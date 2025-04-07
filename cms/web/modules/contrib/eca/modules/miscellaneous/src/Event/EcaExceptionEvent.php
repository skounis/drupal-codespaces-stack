<?php

namespace Drupal\eca_misc\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is dispatched when a routing exception 4xx or 5xx is found.
 */
class EcaExceptionEvent extends Event {

  const EVENT_NAME = 'eca_misc.exception';

  /**
   * Constructs the exception event.
   *
   * @param int $statusCode
   *   The status code.
   */
  public function __construct(
    protected int $statusCode,
  ) {}

  /**
   * Gets the status code.
   *
   * @return int
   *   The status code.
   */
  public function getStatusCode(): int {
    return $this->statusCode;
  }

}
