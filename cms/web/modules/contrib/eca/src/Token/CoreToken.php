<?php

namespace Drupal\eca\Token;

use Drupal\Core\Utility\Token;

/**
 * The ECA token service, which is a decorator for the core token service.
 *
 * @see \Drupal\eca\Token\TokenServices
 */
class CoreToken extends Token implements TokenInterface {

  use TokenDecoratorTrait;

  /**
   * The decorated token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

}
