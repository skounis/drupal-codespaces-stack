<?php

namespace Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api_autocomplete\Search\SearchPluginDeriverBase;

/**
 * Derives a search plugin definition for every view.
 *
 * @see \Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search\Views
 */
class ViewsDeriver extends SearchPluginDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $this->derivatives = [];

      try {
        /** @var \Drupal\Core\Entity\EntityStorageInterface $views_storage */
        $views_storage = $this->getEntityTypeManager()->getStorage('view');
      }
      catch (PluginException $e) {
        return $this->derivatives;
      }

      /** @var \Drupal\views\ViewEntityInterface $view */
      foreach ($views_storage->loadMultiple() as $view) {
        $index = SearchApiQuery::getIndexFromTable($view->get('base_table'));
        if (!($index instanceof IndexInterface)) {
          continue;
        }
        $this->derivatives[$view->id()] = [
          'label' => $view->label(),
          'index' => $index->id(),
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
