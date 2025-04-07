<?php

namespace Drupal\dashboard;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a dashboard entity type.
 */
interface DashboardInterface extends ConfigEntityInterface {

  /**
   * Returns the weight.
   *
   * @return int
   *   The weight of this dashboard.
   */
  public function getWeight();

  /**
   * Sets the weight to the given value.
   *
   * @param int $weight
   *   The desired weight.
   *
   * @return $this
   */
  public function setWeight($weight);

}
