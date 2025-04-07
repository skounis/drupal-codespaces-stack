<?php

namespace Drupal\eca_render\Event;

/**
 * Dispatched when a lazy ECA element is being rendered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderLazyEvent extends EcaRenderEventBase {

  /**
   * The name that identifies the lazy element for the event.
   *
   * @var string
   */
  public string $name;

  /**
   * An optional argument for rendering the element.
   *
   * @var string
   */
  public string $argument;

  /**
   * The render array build.
   *
   * @var array
   */
  public array $build;

  /**
   * Constructs a new EcaRenderLazyEvent object.
   *
   * @param string $name
   *   The name that identifies the lazy element for the event.
   * @param string $argument
   *   An optional argument for rendering the element.
   * @param array &$build
   *   The render array build.
   */
  public function __construct(string $name, string $argument, array &$build) {
    $this->name = $name;
    $this->argument = $argument;
    $this->build = &$build;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

}
