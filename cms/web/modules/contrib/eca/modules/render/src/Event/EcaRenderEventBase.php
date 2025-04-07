<?php

namespace Drupal\eca_render\Event;

use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class of ECA render events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
abstract class EcaRenderEventBase extends Event implements RenderEventInterface {

  /**
   * An instance holding event data accessible as Token.
   *
   * @var \Drupal\eca\Plugin\DataType\DataTransferObject|null
   */
  protected ?DataTransferObject $eventData = NULL;

}
