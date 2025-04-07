<?php

namespace Drupal\eca_views\Event;

use Drupal\views\ViewExecutable;

/**
 * Provides an event when a view gets pre viewed.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_views\Event
 */
class PreView extends ViewsBase {

  /**
   * The machine name of the active display.
   *
   * @var string
   */
  protected string $displayId;

  /**
   * An array of arguments passed into the view.
   *
   * @var array
   */
  protected array $args;

  /**
   * Constructs the ECA views event PreView.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param string $display_id
   *   The machine name of the active display.
   * @param array $args
   *   An array of arguments passed into the view.
   */
  public function __construct(ViewExecutable $view, string $display_id, array &$args) {
    parent::__construct($view);
    $this->displayId = $display_id;
    $this->args = &$args;
  }

}
