<?php

namespace Drupal\bpmn_io\Services\Converter;

use Drupal\eca\Entity\Eca;

/**
 * Converts an ECA-model to use BPMN.io.
 */
interface ConverterInterface {

  /**
   * Converts a given ECA-entity to use BPMN.io as a modeller.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA-entity to convert.
   *
   * @return array
   *   Returns the render array to convert the model to BPMN.io.
   */
  public function convert(Eca $eca): array;

}
