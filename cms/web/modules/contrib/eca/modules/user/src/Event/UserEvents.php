<?php

namespace Drupal\eca_user\Event;

/**
 * Contains all events triggered by the user module regarding users.
 */
final class UserEvents {

  public const LOGIN = 'eca.user.login';

  public const LOGOUT = 'eca.user.logout';

  public const CANCEL = 'eca.user.cancel';

}
