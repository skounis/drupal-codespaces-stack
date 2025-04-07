<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Base class for form-related actions.
 */
abstract class FormActionBase extends ConfigurableActionBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->getCurrentFormState() ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
