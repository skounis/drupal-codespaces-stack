<?php

namespace Drupal\klaro;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\JsCollectionRenderer;

/**
 * Renders JavaScript assets.
 */
class KlaroJsCollectionRenderer extends JsCollectionRenderer implements AssetCollectionRendererInterface {

  /**
   * {@inheritdoc}
   *
   * This class evaluates the aggregation enabled/disabled condition on a group
   * by group basis by testing whether an aggregate file has been made for the
   * group rather than by testing the site-wide aggregation setting. This allows
   * this class to work correctly even if modules have implemented custom
   * logic for grouping and aggregating files.
   */
  public function render(array $js_assets) {

    return array_map(function ($js_asset, $element) {
      if (isset($js_asset['klaro']) && !empty($js_asset['klaro'])) {
        $element['#attributes']['data-type'] = 'text/javascript';
        $element['#attributes']['data-name'] = $js_asset['klaro'];
        if (!empty($element['#attributes']['src'])) {
          $element['#attributes']['data-src'] = $element['#attributes']['src'];
          // @phpstan-ignore-next-line
          $modulePath = base_path() . \Drupal::service('extension.list.module')->getPath('klaro');
          // To support attached libraries via add_js ajax command,
          // we need to fake the load event, so that behaviors get reattached,
          // therefore load an empty js - noop.js.
          $element['#attributes']['src'] = $modulePath . '/js/klaro_placeholder.js';
        }
      }
      return $element;
    }, $js_assets, parent::render($js_assets));

  }

}
