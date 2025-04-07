<?php

namespace Drupal\eca_endpoint\Plugin\Action;

/**
 * Get the content type of the request.
 *
 * @Action(
 *   id = "eca_endpoint_get_request_content_type",
 *   label = @Translation("Request: Get content type"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetRequestContentType extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): ?string {
    if ($request = $this->getRequest()) {
      return $request->getContentTypeFormat();
    }
    return '';
  }

}
