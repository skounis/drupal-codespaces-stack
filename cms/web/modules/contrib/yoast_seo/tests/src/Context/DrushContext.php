<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Drupal\DrupalExtension\Context\DrushContext as BaseDrushContext;

/**
 * Allows using the DrupalExtension's context without cleaning up.
 */
class DrushContext extends BaseDrushContext {

  use AvoidCleanupTrait;

}
