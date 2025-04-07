<?php

namespace Drupal\eca_views\Event;

use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Provides an event when a view query gets altered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_views\Event
 */
class QueryAlter extends ViewsBase {

  /**
   * The query plugin object for the query.
   *
   * @var \Drupal\views\Plugin\views\query\QueryPluginBase
   */
  protected QueryPluginBase $query;

  /**
   * Constructs the ECA views event QueryAlter.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\views\Plugin\views\query\QueryPluginBase $query
   *   The query plugin object for the query.
   */
  public function __construct(ViewExecutable $view, QueryPluginBase $query) {
    parent::__construct($view);
    $this->query = $query;
  }

}
