<?php

namespace Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\search_api_autocomplete\Search\SearchPluginDeriverBase;

/**
 * Derives a search plugin definition for every view.
 *
 * @see \Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search\Page
 */
class PageDeriver extends SearchPluginDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $this->derivatives = [];

      try {
        $page_storage = $this->getEntityTypeManager()
          ->getStorage('search_api_page');
        $index_storage = $this->getEntityTypeManager()
          ->getStorage('search_api_index');
      }
      catch (PluginException $e) {
        return $this->derivatives;
      }

      /** @var \Drupal\search_api_page\SearchApiPageInterface $page */
      foreach ($page_storage->loadMultiple() as $page) {
        $index = $index_storage->load($page->getIndex());
        $this->derivatives[$page->id()] = [
          'label' => $page->label(),
          'index' => $index->id(),
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
