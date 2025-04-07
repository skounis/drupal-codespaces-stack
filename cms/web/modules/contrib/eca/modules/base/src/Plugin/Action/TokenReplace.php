<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Action to recursively replace tokens.
 *
 * @Action(
 *   id = "eca_token_replace",
 *   label = @Translation("Token: replace"),
 *   description = @Translation("Replace all tokens, using a recursive strategy."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class TokenReplace extends TokenSetValue {

  /**
   * {@inheritdoc}
   */
  protected function setToken(string $name, mixed $value): void {
    if ($value instanceof DataTransferObject) {
      $value = $value->count() ? $value->toArray() : $value->getString();
    }
    elseif (($value instanceof TypedDataInterface) && !($value instanceof FieldItemListInterface) && !($value instanceof FieldItemInterface)) {
      $value = $value->getValue();
    }
    if ($value instanceof MarkupInterface) {
      $value = (string) $value;
    }

    $replace_recursive = function (mixed &$value) {
      if (!is_scalar($value) && !is_null($value) && !($value instanceof MarkupInterface)) {
        throw new \InvalidArgumentException(sprintf("Cannot replace tokens using a value of type %s.", is_object($value) ? get_class($value) : gettype($value)));
      }
      if (is_object($value)) {
        $value = (string) $value;
      }
      if (is_string($value)) {
        $limit = 5;
        for ($i = 0; $i < $limit; $i++) {
          $new_value = (string) $this->tokenService->replaceClear($value);
          if ($new_value === $value) {
            break;
          }
          $value = $new_value;
        }
      }
    };

    if (is_scalar($value)) {
      $replace_recursive($value);
    }
    elseif (is_array($value)) {
      array_walk_recursive($value, $replace_recursive);
    }
    elseif ($value instanceof FieldItemListInterface) {
      $item_list = $value;
      $item_values = $item_list->getValue();
      array_walk_recursive($item_values, $replace_recursive);
      $item_list->setValue($item_values);
    }
    elseif ($value instanceof FieldItemInterface) {
      $item = $value;
      $item_value = $item->getValue();
      array_walk_recursive($item_value, $replace_recursive);
      $item->setValue($item_value);
    }
    else {
      throw new \InvalidArgumentException(sprintf("Cannot replace tokens using a value of type %s.", is_object($value) ? get_class($value) : gettype($value)));
    }

    parent::setToken($name, $value);
  }

}
