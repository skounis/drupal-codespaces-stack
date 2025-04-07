<?php

namespace Drupal\eca_endpoint\Plugin\Action;

/**
 * Get the request method.
 *
 * @Action(
 *   id = "eca_endpoint_get_request_method",
 *   label = @Translation("Request: Get method"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetRequestMethod extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): string {
    return $this->getRequest()->getMethod();
  }

}
