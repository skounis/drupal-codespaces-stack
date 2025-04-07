<?php

namespace Drupal\eca_render\Event;

/**
 * Dispatched when local tasks are being rendered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderLocalTasksEvent extends EcaRenderEventBase {

  /**
   * The route name.
   *
   * @var string
   */
  protected string $routeName;

  /**
   * The data array.
   *
   * @var array
   */
  protected array $data;

  /**
   * The render array build.
   *
   * @var array
   */
  protected array $build;

  /**
   * Constructs a new EcaRenderLocalTasksEvent object.
   *
   * @param string $routeName
   *   The route name.
   * @param array &$data
   *   The data array.
   * @param array &$build
   *   The render array build.
   */
  public function __construct(string $routeName, array &$data, array &$build) {
    $this->routeName = $routeName;
    $this->data = &$data;
    $this->build = &$build;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

  /**
   * Get the current links array.
   *
   * @return array
   *   The links array.
   */
  public function &getData(): array {
    return $this->data;
  }

}
