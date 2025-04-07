<?php

namespace Drupal\editoria11y\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\editoria11y\DashboardInterface;

/**
 * Provides route responses for the Editoria11y module.
 */
final class DashboardController extends ControllerBase {
  /**
   * Dashboard property.
   *
   * @var \Drupal\editoria11y\Dashboard
   */
  protected $dashboard;

  /**
   * Constructs a DashboardController object.
   *
   * @param \Drupal\editoria11y\DashboardInterface $dashboard
   *   Dashboard property.
   */
  public function __construct(DashboardInterface $dashboard) {
    $this->dashboard = $dashboard;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new self(
      $container->get('editoria11y.dashboard'),
    );
  }

  /**
   * Get a list of export links.
   *
   * @return array
   *   A simple renderable array.
   */
  public function getExportLinks(): array {
    $links = [];

    $options = [
      'attributes' => [
        'download' => [
          'download',
        ],
      ],
    ];

    $summaryUrl = Url::fromRoute('editoria11y.exports_summary', [], $options);
    $links[] = Link::fromTextAndUrl($this->t('Download summary report'), $summaryUrl)->toString();

    $issuesUrl = Url::fromRoute('editoria11y.exports_issues', [], $options);
    $links[] = Link::fromTextAndUrl($this->t('Download issues report', [], ['context' => 'problems']), $issuesUrl)->toString();

    $dismissalsUrl = Url::fromRoute('editoria11y.exports_dismissals', [], $options);
    $links[] = Link::fromTextAndUrl($this->t('Download dismissals report'), $dismissalsUrl)->toString();

    return $links;
  }

  /**
   * Page: summary dashboard with three panels.
   *
   * @return array
   *   A simple renderable array.
   */
  public function dashboard(): array {

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-container'],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-container', 'layout-row'],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['layout-column', 'layout-column--half'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->t('Top issues', [], ['context' => 'problems']),
          ],
          [
            views_embed_view('editoria11y_results', 'block_top_issues'),
          ],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['layout-column', 'layout-column--half'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->t('Pages with the most issues', [], ['context' => 'problems']),
          ],
          [
            views_embed_view('editoria11y_results', 'block_top_results'),
          ],
        ],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-container'],
        ],

          [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->t('Recent issues', [], ['context' => 'problems']),
          ],
          [
            views_embed_view('editoria11y_results', 'block_recent_issues'),
          ],

      ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['layout-container'],
          ],
            [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Recent dismissals'),
            ],
            [
              views_embed_view('editoria11y_dismissals', 'recent_dismissals'),
            ],
        ],
        [
          '#type' => 'container',
          [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['layout-container'],
            ],
            [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Export results'),
            ],
            [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#items' => $this->getExportLinks(),
            ],
          ],

        ],
    ];
  }

}
