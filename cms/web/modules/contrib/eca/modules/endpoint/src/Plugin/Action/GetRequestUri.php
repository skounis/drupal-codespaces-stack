<?php

namespace Drupal\eca_endpoint\Plugin\Action;

/**
 * Get the requested uri.
 *
 * @Action(
 *   id = "eca_endpoint_get_request_uri",
 *   label = @Translation("Request: Get uri"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetRequestUri extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): string {
    return $this->getRequest()->getRequestUri();
  }

}
