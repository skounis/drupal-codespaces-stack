<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Base class for rendering-related actions.
 */
abstract class RenderActionBase extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof RenderEventInterface ? AccessResult::allowed() : AccessResult::forbidden("The given event is not a rendering event.");
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Get the targeted render element of the given render array.
   *
   * @param string|array $name
   *   The element machine name as string or normalized array.
   * @param array &$build
   *   The render array build.
   *
   * @return array|null
   *   The target element, or NULL if not found.
   */
  protected function &getTargetElement($name, array &$build): ?array {
    if (is_string($name)) {
      $name = $this->getElementNameAsArray($name);
    }

    $nothing = NULL;

    $key_exists = FALSE;
    $value = &NestedArray::getValue($build, $name, $key_exists);
    if ($key_exists) {
      return $value;
    }

    return $nothing;
  }

  /**
   * Get a single element name as normalized array.
   *
   * @return array
   *   The normalized array.
   */
  protected function getElementNameAsArray(string $name): array {
    // Although not officially supported, try to get a target element using
    // either "." or ":" as a separator for nested elements. The official
    // separator format is "][", which will be used for another try here.
    if (mb_strpos($name, '.')) {
      $name = str_replace('.', '][', $name);
    }
    if (mb_strpos($name, ':')) {
      $name = str_replace(':', '][', $name);
    }
    return array_filter(explode('[', str_replace(']', '[', $name)), static function ($value) {
      return $value !== '';
    });
  }

  /**
   * Helper callback that always returns FALSE.
   *
   * Some machine name fields cannot have a check whether they are already in
   * use. For these elements, this method can be used.
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function alwaysFalse(): bool {
    return FALSE;
  }

}
