<?php

namespace Drupal\editoria11y\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\views\ResultRow;

/**
 * Render a field as a link to the pages by issue view.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("editoria11y_pages_by_issue_link")
 */
class PagesByIssueLink extends Standard {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = parent::render($values);

    if (!empty($value)) {

      if (isset($values->editoria11y_results_result_name)) {
        $issue_name = $values->editoria11y_results_result_name;
        $url = Url::fromUserInput("/admin/reports/editoria11y/issue", [
          'query' => [
            'q' => $issue_name,
          ],
        ]);

        $value = Link::fromTextAndUrl($value, $url)->toString();
      }

    }

    return $value;
  }

}
