<?php

namespace Drupal\editoria11y\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\views\ResultRow;

/**
 * Render a value to the page.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("editoria11y_page_link")
 */
class PageLink extends Standard {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = parent::render($values);

    if (!empty($value)) {
      $path = '';

      if (property_exists($values, 'editoria11y_results_page_path')) {
        $path = $values->editoria11y_results_page_path;
      }
      elseif (property_exists($values, 'editoria11y_dismissals_page_path')) {
        $path = $values->editoria11y_dismissals_page_path;
      }
      else {
        return $value . ' ' . t('(invalid URL)');
      }

      // @phpstan-ignore-next-line
      $config = \Drupal::config('editoria11y.settings');
      $prefix = $config->get('redundant_prefix');
      if (!empty($prefix)) {
        // Replace first instance.
        $pos = strpos($path, $prefix);
        if ($pos !== FALSE) {
          $path = substr_replace($path, "", $pos, strlen($prefix));
        }
      }

      // Multilingual validation is a pain and a performance concern:
      // https://www.drupal.org/project/drupal/issues/2994575#comment-14863919
      // $url = \Drupal::service('path.validator')->getUrlIfValidWithoutAccessCheck($path);
      $url = Url::fromUserInput($path);
      if (!$url) {
        return $value . ' ' . t('(invalid URL)');
      }

      $url->mergeOptions(['query' => ['ed1ref' => $path]]);
      $value = Link::fromTextAndUrl($value, $url)->toString();

    }

    return $value;
  }

}
