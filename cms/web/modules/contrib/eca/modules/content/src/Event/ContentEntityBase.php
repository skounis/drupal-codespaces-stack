<?php

namespace Drupal\eca_content\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for entity related events.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
abstract class ContentEntityBase extends Event {

}
