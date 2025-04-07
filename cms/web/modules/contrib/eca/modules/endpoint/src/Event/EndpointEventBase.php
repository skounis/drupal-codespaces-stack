<?php

namespace Drupal\eca_endpoint\Event;

use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class of ECA endpoint events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_endpoint\Event
 */
abstract class EndpointEventBase extends Event {

  /**
   * The arguments provided in the URL path.
   *
   * @var array
   */
  public array $pathArguments;

  /**
   * An instance holding event data accessible as Token.
   *
   * @var \Drupal\eca\Plugin\DataType\DataTransferObject|null
   */
  protected ?DataTransferObject $eventData = NULL;

}
