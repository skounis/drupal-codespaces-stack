<?php

/**
 * @file
 * Hooks for the sam module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allows modules to alter the list of supported widget types.
 *
 * Modules implementing this are responsible for ensuring that the way this
 * module shows/hides new elements works well with their widget.
 *
* @param array $widget_types
 *   An indexed array with supported widget machine names, to be modified by
 *   reference.

 */
function hook_sam_allowed_widget_types_alter(array &$widget_types) {
  $widget_types[] = 'my_custom_widget';
}

/**
 * @} End of "addtogroup hooks".
 */
