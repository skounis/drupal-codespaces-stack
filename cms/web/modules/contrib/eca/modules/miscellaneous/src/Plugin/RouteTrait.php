<?php

namespace Drupal\eca_misc\Plugin;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Trait for route related actions and conditions.
 *
 * @see \Drupal\eca_misc\Plugin\Action\TokenLoadRouteParameter
 * @see \Drupal\eca_misc\Plugin\ECA\Condition\RouteMatch
 */
trait RouteTrait {

  use PluginFormTrait;

  /**
   * Builds and returns the route match depending on the plugin configuration.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The route match applicable to the current configuration.
   */
  protected function getRouteMatch(): RouteMatchInterface {
    /** @var \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch */
    $currentRouteMatch = \Drupal::service('current_route_match');
    // @todo Consider using the match function provided by PHP 8.
    $request = $this->configuration['request'];
    if ($request === '_eca_token') {
      $request = $this->getTokenValue('request', '');
    }
    switch ($request) {
      case RouteInterface::ROUTE_MAIN:
        return $currentRouteMatch->getMasterRouteMatch();

      case RouteInterface::ROUTE_PARENT:
        return $currentRouteMatch->getParentRouteMatch();

      case RouteInterface::ROUTE_CURRENT:
      default:
        return $currentRouteMatch;

    }
  }

  /**
   * Provides a form field for ECA modellers to select the request type.
   *
   * Builds the configuration form for route related plugins to decide, which
   * request (main, parent or current) should be used for route matches.
   *
   * @param array $form
   *   The form to which the config field should be added.
   */
  protected function requestFormField(array &$form): void {
    $form['request'] = [
      '#type' => 'select',
      '#title' => $this->t('Request'),
      '#description' => $this->t('The request route match.'),
      '#default_value' => $this->configuration['request'],
      '#options' => [
        RouteInterface::ROUTE_CURRENT => $this->t('current'),
        RouteInterface::ROUTE_PARENT => $this->t('parent'),
        RouteInterface::ROUTE_MAIN => $this->t('main'),
      ],
      '#weight' => -110,
      '#eca_token_select_option' => TRUE,
    ];
  }

}
