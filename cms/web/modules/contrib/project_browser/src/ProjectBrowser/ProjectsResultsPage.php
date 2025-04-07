<?php

namespace Drupal\project_browser\ProjectBrowser;

use Drupal\Component\Assertion\Inspector;

/**
 * One page of search results from a query.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
final class ProjectsResultsPage {

  /**
   * Constructs a single page of results to display in a project browser.
   *
   * @param int $totalResults
   *   Total number of results, across all pages.
   * @param \Drupal\project_browser\ProjectBrowser\Project[] $list
   *   A numerically indexed array of projects to display on this page.
   * @param string $pluginLabel
   *   The source plugin's label.
   * @param string $pluginId
   *   The source plugin's ID.
   * @param string|null $error
   *   (optional) Error to pass along, if any.
   */
  public function __construct(
    public readonly int $totalResults,
    public readonly array $list,
    public readonly string $pluginLabel,
    public readonly string $pluginId,
    public readonly ?string $error = NULL,
  ) {
    assert(array_is_list($list));
    assert(Inspector::assertAllObjects($list, Project::class));
  }

  /**
   * Returns the contents of this object as an array.
   *
   * @return array
   *   The contents of this object, as an array.
   */
  public function toArray(): array {
    return get_object_vars($this);
  }

}
