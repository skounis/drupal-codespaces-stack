<?php

namespace Drupal\eca_endpoint\Plugin\Action;

/**
 * Get the request content.
 *
 * @Action(
 *   id = "eca_endpoint_get_request_content",
 *   label = @Translation("Request: Get content"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetRequestContent extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): string {
    return (string) $this->getRequest()->getContent();
  }

}
