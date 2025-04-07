<?php

namespace Drupal\editoria11y\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;

/**
 * Render a count as a link to the issues by page view.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("editoria11y_issues_by_page_link")
 */
class IssuesByPageLink extends NumericField {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = parent::render($values);

    if (!empty($value)) {

      $path = property_exists($values, 'editoria11y_results_page_path') ? $values->editoria11y_results_page_path : '';

      $url = Url::fromUserInput("/admin/reports/editoria11y/page", [
        'query' => [
          'q' => $path,
        ],
      ]);

      $value = Link::fromTextAndUrl($value, $url)->toString();
    }

    return $value;
  }

}
