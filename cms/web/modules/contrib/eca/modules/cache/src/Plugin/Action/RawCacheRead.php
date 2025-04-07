<?php

namespace Drupal\eca_cache\Plugin\Action;

/**
 * Action to read from raw cache.
 *
 * @Action(
 *   id = "eca_raw_cache_read",
 *   label = @Translation("Cache Raw: read"),
 *   description = @Translation("Read a value item from raw cache and store it as a token."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class RawCacheRead extends CacheRead {

}
