<?php

namespace Drupal\eca_views;

use Drupal\eca\Event\BaseHookHandler;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * The handler for hook implementations within the eca_views.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  /**
   * Dispatches event query substitutions.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   *
   * @return array
   *   The list of substitutions.
   */
  public function querySubstitutions(ViewExecutable $view): array {
    /** @var \Drupal\eca_views\Event\QuerySubstitutions $event */
    $event = $this->triggerEvent->dispatchFromPlugin('eca_views:query_substitutions', $view);
    return $event->getSubstitutions();
  }

  /**
   * Dispatches event pre view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param string $display_id
   *   The machine name of the active display.
   * @param array $args
   *   An array of arguments passed into the view.
   */
  public function preView(ViewExecutable $view, string $display_id, array &$args): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_view', $view, $display_id, $args);
  }

  /**
   * Dispatches event pre build.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function preBuild(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_build', $view);
  }

  /**
   * Dispatches event post build.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function postBuild(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:post_build', $view);
  }

  /**
   * Dispatches event pre execute.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function preExecute(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_execute', $view);
  }

  /**
   * Dispatches event post execute.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function postExecute(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:post_execute', $view);
  }

  /**
   * Dispatches event pre render.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  public function preRender(ViewExecutable $view): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:pre_render', $view);
  }

  /**
   * Dispatches event post render.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param array $output
   *   A structured content array representing the view output.
   */
  public function postRender(ViewExecutable $view, array &$output): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:post_render', $view, $output);
  }

  /**
   * Dispatches event query alter.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\views\Plugin\views\query\QueryPluginBase $query
   *   The query plugin object for the query.
   */
  public function queryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    $this->triggerEvent->dispatchFromPlugin('eca_views:query_alter', $view, $query);
  }

}
