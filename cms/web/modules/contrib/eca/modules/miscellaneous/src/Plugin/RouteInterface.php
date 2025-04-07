<?php

namespace Drupal\eca_misc\Plugin;

/**
 * Interface for route related actions and conditions.
 */
interface RouteInterface {

  public const ROUTE_MAIN = 0;
  public const ROUTE_PARENT = 1;
  public const ROUTE_CURRENT = 2;

}
