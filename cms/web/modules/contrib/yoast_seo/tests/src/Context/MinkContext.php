<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Drupal\DrupalExtension\Context\MinkContext as BaseMinkContext;

/**
 * Allows using the DrupalExtension's context without cleaning up.
 */
class MinkContext extends BaseMinkContext {

  use AvoidCleanupTrait;

}
