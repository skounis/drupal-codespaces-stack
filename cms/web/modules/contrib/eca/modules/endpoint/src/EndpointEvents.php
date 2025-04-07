<?php

namespace Drupal\eca_endpoint;

/**
 * Defines events provided by the ECA Endpoint module.
 */
final class EndpointEvents {

  /**
   * Dispatches when an ECA Endpoint response is being rendered.
   *
   * @Event
   *
   * @var string
   */
  public const RESPONSE = 'eca_endpoint.response';

  /**
   * Dispatches when an ECA Endpoint is being checked for access.
   *
   * @Event
   *
   * @var string
   */
  public const ACCESS = 'eca_endpoint.access';

}
