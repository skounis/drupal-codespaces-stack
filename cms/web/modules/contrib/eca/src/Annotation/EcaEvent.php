<?php

namespace Drupal\eca\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines ECA event annotation object.
 *
 * @Annotation
 */
class EcaEvent extends Plugin {

  /**
   * Label of the event.
   *
   * @var string
   */
  public string $label;

  /**
   * Name of the event being covered.
   *
   * @var string
   */
  public string $event_name;

  /**
   * Priority when subscribing to the covered event.
   *
   * @var int
   */
  public int $subscriber_priority;

  /**
   * Event class to which this ECA event subscribes.
   *
   * @var string
   */
  public string $event_class;

  /**
   * Tag for event characterization.
   *
   * @var int
   */
  public int $tags;

}
