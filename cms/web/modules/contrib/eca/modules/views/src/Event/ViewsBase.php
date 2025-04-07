<?php

namespace Drupal\eca_views\Event;

use Drupal\views\ViewExecutable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for views related events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class ViewsBase extends Event {

  /**
   * The view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected ViewExecutable $view;

  /**
   * Base constructor for ECA views events.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function __construct(ViewExecutable $view) {
    $this->view = $view;
  }

  /**
   * Gets the view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view.
   */
  public function getView(): ViewExecutable {
    return $this->view;
  }

}
