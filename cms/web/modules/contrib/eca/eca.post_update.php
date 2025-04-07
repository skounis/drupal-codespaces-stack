<?php

/**
 * @file
 * Post update functions for ECA.
 */

/**
 * Rename used tokens in available ECA models for ECA 2.0.0.
 */
function eca_post_update_rename_tokens_2_0_0(): void {
  $tokenNames = [
    'event:content-type' => 'event:content_type',
    'event:entity-bundle' => 'event:entity_bundle',
    'event:entity-id' => 'event:entity_id',
    'event:entity-type' => 'event:entity_type',
    'event:extra-field-name' => 'event:extra_field_name',
    'event:field-name' => 'event:field_name',
    'event:machine-name' => 'event:machine_name',
    'event:parent-bundle' => 'event:parent_bundle',
    'event:parent-id' => 'event:parent_id',
    'event:parent-type' => 'event:parent_type',
    'event:path-arguments' => 'event:path_arguments',
    'event:route-parameters' => 'event:route_parameters',
    'event:view-display' => 'event:view_display',
    'event:view-id' => 'event:view_id',
    'form:base-id' => 'form:base_id',
    'form:num-errors' => 'form:num_errors',
  ];
  _eca_post_update_token_rename($tokenNames);
}

/**
 * Re-run the 2.0.0 post update hook.
 *
 * @see https://www.drupal.org/project/eca/issues/3460491
 */
function eca_post_update_rename_tokens_2_0_1(): void {
  eca_post_update_rename_tokens_2_0_0();
}
