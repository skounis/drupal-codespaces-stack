<?php

declare(strict_types=1);

namespace Drupal\trash\RouteProcessor;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\RouteProcessor\OutboundRouteProcessorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Processes outbound routes for trashed entities.
 */
class TrashRouteProcessor implements OutboundRouteProcessorInterface {

  public function __construct(
    protected RequestStack $requestStack,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($route_name, Route $route, array &$parameters, ?BubbleableMetadata $bubbleable_metadata = NULL): void {
    // Add our 'in_trash' parameter to routes that need it.
    if ($route->hasOption('_trash_route')) {
      $parameters['in_trash'] = TRUE;
    }

    // Check if we're viewing a deleted entity and ensure that any other links
    // displayed on the page (e.g. local tasks) have the proper trash context.
    $request = $this->requestStack->getCurrentRequest();
    $current_route = $this->routeMatch->getRouteObject();
    if (!$request || !$current_route) {
      return;
    }

    if ($request->query->has('in_trash')) {
      $parts = explode('.', $this->routeMatch->getRouteName());
      if ($parts[0] === 'entity') {
        $entity_type_id = $parts[1];
        $entity_id = $this->routeMatch->getRawParameter($entity_type_id);

        if (isset($parameters[$entity_type_id]) && $parameters[$entity_type_id] === $entity_id) {
          $parameters['in_trash'] = TRUE;
        }
      }
    }
  }

}
