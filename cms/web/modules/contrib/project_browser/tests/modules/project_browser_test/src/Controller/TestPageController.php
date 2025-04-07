<?php

declare(strict_types=1);

namespace Drupal\project_browser_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns a test page for Project Browser.
 */
final class TestPageController extends ControllerBase {

  /**
   * Renders the Project Browser test page.
   *
   * @param \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface $source
   *   The source plugin to query for projects.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array.
   */
  public function render(ProjectBrowserSourceInterface $source, Request $request): array {
    $sort_options = $request->get('sort_options', []);
    if ($sort_options === 'none') {
      $sort_options = array_slice($source->getSortOptions(), 0, 1);
    }
    else {
      $sort_options = array_merge($source->getSortOptions(), $sort_options);
    }

    $filters = $request->get('filters');
    if ($filters === 'none') {
      $filters = [];
    }

    $build = [];
    for ($i = 0; $i < $request->query->getInt('instances', 1); $i++) {
      $build[$i] = [
        '#type' => 'project_browser',
        '#source' => $source,
        '#cache' => [
          'contexts' => ['url.query_args'],
        ],
        '#sort_options' => $sort_options,
        '#filters' => $filters,
      ];
    }
    return $build;
  }

}
