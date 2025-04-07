<?php

namespace Drupal\eca_render\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a lazy render element, for being built up via ECA.
 *
 * @RenderElement("eca_lazy")
 */
class EcaLazy extends RenderElementBase {

  /**
   * {@inheritdoc}
   *
   * Generate the placeholder in a #pre_render callback, because the hash salt
   * needs to be accessed, which may not yet be available when this is called.
   */
  public function getInfo() {
    return [
      '#name' => '',
      '#argument' => NULL,
      '#pre_render' => [
        __CLASS__ . '::generatePlaceholder',
      ],
    ];
  }

  /**
   * Pre render callback to generate a placeholder.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array containing the placeholder.
   */
  public static function generatePlaceholder(array $element) {
    $build = [
      '#lazy_builder' => [
        __CLASS__ . '::buildElement', [
          $element['#name'],
          $element['#argument'],
        ],
      ],
      '#create_placeholder' => TRUE,
    ];

    // Directly create a placeholder as we need this to be placeholdered
    // regardless if this is a POST or GET request.
    // @todo remove this when https://www.drupal.org/node/2367555 lands.
    $build = \Drupal::service('render_placeholder_generator')->createPlaceholder($build);

    return $build;
  }

  /**
   * Lazy builder callback that triggers the ECA lazy element event.
   *
   * @param string $name
   *   The name that identifies the rendering process for ECA.
   * @param string $argument
   *   (optional) A string that contains an arbitrary argument.
   *
   * @return array
   *   A renderable array.
   */
  public static function buildElement(string $name, string $argument = '') {
    $render = [];
    _eca_trigger_event()->dispatchFromPlugin('eca_render:lazy_element', $name, $argument, $render);
    return $render;
  }

}
