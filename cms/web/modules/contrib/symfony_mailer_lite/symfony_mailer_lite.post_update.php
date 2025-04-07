<?php

/**
 * @file
 * Post update functions for the Symfony Mailer Lite module.
 */

declare(strict_types = 1);

/**
 * Set the 'override' option.
 */
function symfony_mailer_lite_post_update_set_override_option() {
  $config = \Drupal::configFactory()->getEditable('symfony_mailer_lite.message');
  $config->set('override', FALSE)->save();
}
