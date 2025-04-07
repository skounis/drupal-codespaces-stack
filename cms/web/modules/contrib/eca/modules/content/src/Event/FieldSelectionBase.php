<?php

namespace Drupal\eca_content\Event;

use Drupal\eca\Event\ContentEntityEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for field selection events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class FieldSelectionBase extends Event implements ContentEntityEventInterface {}
