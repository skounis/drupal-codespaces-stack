<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_endpoint\Event\EndpointResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Base class for response actions.
 */
abstract class ResponseActionBase extends ConfigurableActionBase {

  /**
   * Get the current response.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The response, or NULL if not available.
   */
  public function getResponse(): ?Response {
    if ($this->event instanceof EndpointResponseEvent) {
      return $this->event->response;
    }
    if ($this->event instanceof ResponseEvent) {
      return $this->event->getResponse();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->getResponse() ? AccessResult::allowed() : AccessResult::forbidden("No response available.");
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if ($this->getResponse()) {
      $this->doExecute();
    }
  }

  /**
   * Implementation detail of the action execution.
   */
  abstract protected function doExecute(): void;

}
