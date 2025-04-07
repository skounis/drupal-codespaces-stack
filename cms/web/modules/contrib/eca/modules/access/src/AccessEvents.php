<?php

namespace Drupal\eca_access;

/**
 * Defines events provided by the ECA Access module.
 */
final class AccessEvents {

  /**
   * Dispatches when an entity is being asked for access.
   *
   * @Event
   *
   * @var string
   */
  public const ENTITY = 'eca_access.entity';

  /**
   * Dispatches when an entity field is being asked for access.
   *
   * @Event
   *
   * @var string
   */
  public const FIELD = 'eca_access.field';

  /**
   * Dispatches when being asked for access to create an entity.
   *
   * @Event
   *
   * @var string
   */
  public const CREATE = 'eca_access.create';

}
