<?php

namespace Drupal\eca_endpoint\Plugin\Action;

/**
 * Get the client IP.
 *
 * @Action(
 *   id = "eca_endpoint_get_client_ip",
 *   label = @Translation("Request: Get client IP"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetClientIp extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): ?string {
    return $this->getRequest()->getClientIp();
  }

}
