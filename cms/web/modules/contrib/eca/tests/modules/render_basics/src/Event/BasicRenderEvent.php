<?php

namespace Drupal\eca_test_render_basics\Event;

use Drupal\eca\Event\RenderEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Basic render event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class BasicRenderEvent extends Event implements RenderEventInterface {

  /**
   * The render array build.
   *
   * @var array
   */
  public array $build;

  /**
   * Constructs a new BasicRenderEvent object.
   *
   * @param array &$build
   *   The render array build.
   */
  public function __construct(array &$build) {
    $this->build = $build;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

}
