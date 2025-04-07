<?php

namespace Drupal\eca_cache\Plugin\Action;

/**
 * Action to write into raw cache.
 *
 * @Action(
 *   id = "eca_raw_cache_write",
 *   label = @Translation("Cache Raw: write"),
 *   description = @Translation("Write a value item into raw cache."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class RawCacheWrite extends CacheWrite {

}
