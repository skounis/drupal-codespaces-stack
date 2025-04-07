<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Drupal\DrupalExtension\Context\DrupalContext as BaseDrupalContext;

/**
 * Allows using the DrupalExtension's context without cleaning up.
 */
class DrupalContext extends BaseDrupalContext {

  use AvoidCleanupTrait;

}
