<?php

namespace Drupal\geofield\GeoPHP;

/**
 * Provides a GeoPHPInterface.
 */
interface GeoPHPInterface {

  /**
   * Retrieves the GeoPHP library current version.
   *
   * @return string
   *   The version value.
   */
  public function version();

  /**
   * Loads a geometry object given some parameters.
   *
   * @param mixed|null $data
   *   The data to load.
   * @param string $type
   *   The string type.
   *
   * @return \Geometry|null
   *   The geometry object
   */
  public function load($data = NULL, $type = NULL);

  /**
   * Get the Adapter Map.
   *
   * @return mixed
   *   The Adapter Map.
   */
  public function getAdapterMap();

}
