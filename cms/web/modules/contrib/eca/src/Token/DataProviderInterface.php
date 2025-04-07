<?php

namespace Drupal\eca\Token;

/**
 * Interface for Token data providers.
 */
interface DataProviderInterface {

  /**
   * Get Token data.
   *
   * @param string $key
   *   The data key hat is expected to hold the data value.
   *
   * @return mixed
   *   If data for the key exists, the associated data value is being
   *   returned. Otherwise, if it not exists, NULL will be returned.
   */
  public function getData(string $key): mixed;

  /**
   * Determines whether Token data exists.
   *
   * @param string $key
   *   The data key to check for.
   *
   * @return bool
   *   Returns TRUE if data exists, FALSE otherwise.
   */
  public function hasData(string $key): bool;

}
