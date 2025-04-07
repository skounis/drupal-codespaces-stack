<?php

declare(strict_types=1);

namespace Drupal\project_browser\Plugin\ProjectBrowserSource;

use Drupal\Component\Assertion\Inspector;
use Drupal\project_browser\ProjectBrowser\Project;

/**
 * Provides helper methods for sources to sort projects in various ways.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
final class SortHelper {

  /**
   * Sorts projects according to a defined order.
   *
   * Projects that are listed in $order will be moved to the top of the list,
   * and then sorted to match what's in $order. All other projects in will not
   * be moved.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project[] $projects
   *   An array of project objects to sort.
   * @param string[] $order
   *   An indexed array of unqualified project IDs, in the order that they
   *   should appear.
   */
  public static function sortInDefinedOrder(array &$projects, array $order): void {
    assert(
      Inspector::assertAllObjects($projects, Project::class) &&
      Inspector::assertAllStrings($order) &&
      Inspector::assertAllNotEmpty($order) &&
      Inspector::assertStrictArray($order),
      new \InvalidArgumentException('The configured order must be an indexed array of strings.'),
    );

    uasort($projects, static function (Project $a, Project $b) use ($order): int {
      $a_position = array_search($a->id, $order, TRUE);
      $b_position = array_search($b->id, $order, TRUE);

      // If both projects are in the defined order, sort based on that.
      if (is_int($a_position) && is_int($b_position)) {
        return $a_position <=> $b_position;
      }
      // If the first project is in the configured order but the second isn't,
      // the first one goes before the second one.
      elseif (is_int($a_position) && $b_position === FALSE) {
        return -1;
      }
      // If the first project isn't in the configured order but the second one
      // is, the second one goes before the first one.
      elseif ($a_position === FALSE && is_int($b_position)) {
        return 1;
      }
      // If neither project is in the configured order, leave them as-is.
      else {
        return 0;
      }
    });
  }

}
