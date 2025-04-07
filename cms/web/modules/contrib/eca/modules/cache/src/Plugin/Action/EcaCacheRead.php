<?php

namespace Drupal\eca_cache\Plugin\Action;

/**
 * Action to read from ECA cache.
 *
 * @Action(
 *   id = "eca_cache_read",
 *   label = @Translation("Cache ECA: read"),
 *   description = @Translation("Read a value item from ECA cache and store it as a token."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class EcaCacheRead extends CacheRead {

  use EcaCacheTrait;

}
