<?php

namespace Drupal\eca_log;

/**
 * Defines events provided by the ECA Log module.
 */
final class LogEvents {

  /**
   * Dispatches an event when a log message is being created.
   *
   * @Event
   *
   * @var string
   */
  public const MESSAGE = 'eca_log.message';

}
