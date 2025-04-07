<?php

declare(strict_types=1);

namespace Drupal\project_browser\Routing;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\project_browser\EnabledSourceHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Converts route parameters into a Project object.
 *
 * If a route parameter defines a `project_browser.project` option, this will
 * add a Project object to the defaults, getting the source plugin ID and local
 * (source-specific) project ID from other route parameters.
 *
 * For example, consider a route like this:
 * ```
 * path: '/projects/view/{source}/{id}'
 * defaults:
 *   project: null
 * options:
 *   parameters:
 *     project:
 *       project_browser.project: [source, id]
 * ```
 * This will look up a project using the `source` parameter as the source plugin
 * ID, and the `id` parameter as the project's local ID. The project will be
 * passed to the controller's $project parameter.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ProjectEnhancer implements EnhancerInterface {

  public function __construct(
    private readonly EnabledSourceHandler $enabledSources,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request): array {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];

    $parameters = $route->getOption('parameters');
    foreach (array_keys($defaults) as $name) {
      if (isset($parameters[$name]['project_browser.project'])) {
        [$source_id, $local_id] = $parameters[$name]['project_browser.project'];

        if (array_key_exists($source_id, $defaults) && array_key_exists($local_id, $defaults)) {
          // @see \Drupal\project_browser\EnabledSourceHandler::getProjects()
          $id = $defaults[$source_id] . '/' . $defaults[$local_id];
          $defaults[$name] = $this->enabledSources->getStoredProject($id);
        }
      }
    }
    return $defaults;
  }

}
