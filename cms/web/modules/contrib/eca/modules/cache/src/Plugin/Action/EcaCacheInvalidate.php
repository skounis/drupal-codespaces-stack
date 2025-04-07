<?php

namespace Drupal\eca_cache\Plugin\Action;

/**
 * Action to invalidate ECA cache.
 *
 * @Action(
 *   id = "eca_cache_invalidate",
 *   label = @Translation("Cache ECA: invalidate"),
 *   description = @Translation("Invalidates a part or the whole ECA cache."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class EcaCacheInvalidate extends CacheInvalidate {

  use EcaCacheTrait;

}
