<?php

namespace Drupal\eca_cache\Plugin\Action;

/**
 * Action to write into ECA cache.
 *
 * @Action(
 *   id = "eca_cache_write",
 *   label = @Translation("Cache ECA: write"),
 *   description = @Translation("Write a value item into ECA cache."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class EcaCacheWrite extends CacheWrite {

  use EcaCacheTrait;

}
