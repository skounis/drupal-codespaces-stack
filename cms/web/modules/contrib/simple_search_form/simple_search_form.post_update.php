<?php

/**
 * @file
 * Post update functions for Simple Search Form.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Set default value for input_keep_value for all Simple Search Forms.
 */
function simple_search_form_post_update_set_input_keep_value(&$sandbox = NULL) {

  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'block', function ($block) {
    /** @var \Drupal\block\Entity\Block $block */

    if ($block->getPluginId() === 'simple_search_form_block') {
      $settings = $block->get('settings');

      if (!array_key_exists('input_keep_value', $settings)) {

        $settings['input_keep_value'] = FALSE;
        $block->set('settings', $settings);

        return TRUE;
      }
    }

    return FALSE;
  });
}
