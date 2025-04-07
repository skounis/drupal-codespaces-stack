<?php

namespace Drupal\eca_language\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched on language negotiation.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_language\Event
 */
class LanguageNegotiateEvent extends Event {

  /**
   * The negotiated language code.
   *
   * @var string|null
   */
  public ?string $langcode = NULL;

}
