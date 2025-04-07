<?php

/**
 * @file
 * Contains post-update hooks for Automatic Updates.
 *
 * DELETE THIS FILE FROM CORE MERGE REQUEST.
 */

declare(strict_types=1);

/**
 * Implements hook_removed_post_updates().
 */
function automatic_updates_removed_post_updates(): array {
  return [
    'automatic_updates_post_update_create_status_check_mail_config' => '3.0.0',
  ];
}
