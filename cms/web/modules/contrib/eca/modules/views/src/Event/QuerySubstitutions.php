<?php

namespace Drupal\eca_views\Event;

/**
 * Provides an event when a view allows query substitutions.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_views\Event
 */
class QuerySubstitutions extends ViewsBase {

  /**
   * The list of substitutions.
   *
   * @var array
   */
  protected array $substitutions = [];

  /**
   * Get the list of substitutions.
   *
   * @return array
   *   The list of substitutions.
   */
  public function getSubstitutions(): array {
    return $this->substitutions;
  }

  /**
   * Adds a new substitution.
   *
   * @param string $from
   *   The string to substitute.
   * @param string $to
   *   The replacement string.
   */
  public function addSubstitution(string $from, string $to): void {
    $this->substitutions[$from] = $to;
  }

}
