<?php

namespace Drupal\eca_language;

/**
 * Defines events provided by the ECA Language module.
 */
final class LanguageEvents {

  /**
   * Dispatches on language negotiation.
   *
   * @Event
   *
   * @var string
   */
  public const NEGOTIATE = 'eca_language.negotiate';

}
