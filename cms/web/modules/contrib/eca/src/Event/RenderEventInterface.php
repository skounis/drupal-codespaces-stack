<?php

namespace Drupal\eca\Event;

/**
 * Interface for rendering events.
 */
interface RenderEventInterface {

  /**
   * Get the render array.
   *
   * @return array
   *   The render array.
   */
  public function &getRenderArray(): array;

}
