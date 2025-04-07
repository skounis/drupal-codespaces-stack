<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Base class for response actions.
 */
abstract class ResponseAjaxCommandBase extends ResponseActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $this->getResponse() instanceof AjaxResponse ?
      AccessResult::allowed() :
      parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $response = $this->getResponse();
    if ($response instanceof AjaxResponse) {
      $response->addCommand($this->getAjaxCommand());
    }
  }

  /**
   * Get the ajax command, that should be added to the response.
   *
   * @return \Drupal\Core\Ajax\CommandInterface
   *   The ajax command..
   */
  abstract protected function getAjaxCommand(): CommandInterface;

}
