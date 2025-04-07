<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Base class for actions performing operations on data contained in a list.
 */
abstract class ListDataOperationBase extends ListOperationBase {

  /**
   * The database operation to perform on the contained list data.
   *
   * May be one of "save" or "delete".
   *
   * @var string
   */
  protected static string $operation = 'save';

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $list = $this->getItemList();

    if (is_null($list)) {
      $result = AccessResult::forbidden("No list provided.");
    }
    else {
      $access_allowed = TRUE;
      if ($list instanceof DataTransferObject) {
        $list = $list->getSaveables();
      }
      elseif ($list instanceof EntityReferenceFieldItemListInterface) {
        $list = $list->referencedEntities();
      }
      foreach ($list as $v) {
        if ($v instanceof EntityAdapter) {
          $v = $v->getEntity();
        }
        if ($v instanceof EntityInterface) {
          if ($v->isNew()) {
            if (static::$operation === 'delete') {
              $access_allowed = FALSE;
              $reason = "Cannot delete entities that are not yet saved.";
              break;
            }
            elseif (static::$operation === 'save' || static::$operation === 'create') {
              /**
               * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
               */
              $access_handler = $this->entityTypeManager->getHandler($v->getEntityTypeId(), 'access');
              if (!$access_handler->createAccess($v->bundle(), $account, [], FALSE)) {
                $access_allowed = FALSE;
                $reason = "Current user has no access to create new entity.";
                break;
              }
              continue;
            }
          }
        }
        $op = static::$operation === 'save' ? 'update' : static::$operation;
        if (($v instanceof AccessibleInterface) && !$v->access($op, $account)) {
          $access_allowed = FALSE;
          $reason = "At least one entity is not allowed to be {$op}d by the current user.";
          break;
        }
      }
      $result = $access_allowed ? AccessResult::allowed() : AccessResult::forbidden($reason ?? NULL);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($list = $this->getItemList())) {
      return;
    }

    if ($list instanceof DataTransferObject) {
      $list = $list->getSaveables();
    }
    elseif ($list instanceof EntityReferenceFieldItemListInterface) {
      $list = $list->referencedEntities();
    }

    $list_array = [];
    foreach ($list as $k => $v) {
      $list_array[$k] = $v;
    }

    switch (static::$operation) {

      case 'save':
        DataTransferObject::create($list_array)->saveData();
        break;

      case 'delete':
        DataTransferObject::create($list_array)->deleteData();
        break;

    }

  }

}
