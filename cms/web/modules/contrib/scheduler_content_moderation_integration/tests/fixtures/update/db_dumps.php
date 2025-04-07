<?php

/**
 * @file
 * Test fixture to set up db tables from dump files.
 *
 * phpcs:ignoreFile
 * Ignore all coding standards, as some of this file is copied from core db
 * dump files which do not adhere to standards.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Add/update the configurations for workflow and content_moderation.
$connection->insert('key_value')
->fields([
  'collection',
  'name',
  'value',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.entity_schema_data',
  'value' => 'a:4:{s:24:"content_moderation_state";a:2:{s:11:"primary key";a:1:{i:0;s:2:"id";}s:11:"unique keys";a:1:{s:37:"content_moderation_state__revision_id";a:1:{i:0;s:11:"revision_id";}}}s:33:"content_moderation_state_revision";a:2:{s:11:"primary key";a:1:{i:0;s:11:"revision_id";}s:7:"indexes";a:1:{s:28:"content_moderation_state__id";a:1:{i:0;s:2:"id";}}}s:35:"content_moderation_state_field_data";a:3:{s:11:"primary key";a:2:{i:0;s:2:"id";i:1;s:8:"langcode";}s:7:"indexes";a:2:{s:56:"content_moderation_state__id__default_langcode__langcode";a:3:{i:0;s:2:"id";i:1;s:16:"default_langcode";i:2;s:8:"langcode";}s:37:"content_moderation_state__revision_id";a:1:{i:0;s:11:"revision_id";}}s:11:"unique keys";a:1:{s:32:"content_moderation_state__lookup";a:5:{i:0;s:22:"content_entity_type_id";i:1;s:17:"content_entity_id";i:2;s:26:"content_entity_revision_id";i:3;s:8:"workflow";i:4;s:8:"langcode";}}}s:39:"content_moderation_state_field_revision";a:3:{s:11:"primary key";a:2:{i:0;s:11:"revision_id";i:1;s:8:"langcode";}s:7:"indexes";a:1:{s:56:"content_moderation_state__id__default_langcode__langcode";a:3:{i:0;s:2:"id";i:1;s:16:"default_langcode";i:2;s:8:"langcode";}}s:11:"unique keys";a:1:{s:32:"content_moderation_state__lookup";a:5:{i:0;s:22:"content_entity_type_id";i:1;s:17:"content_entity_id";i:2;s:26:"content_entity_revision_id";i:3;s:8:"workflow";i:4;s:8:"langcode";}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.content_entity_id',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:17:"content_entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:0;s:4:"size";s:6:"normal";s:8:"not null";b:0;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:17:"content_entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:0;s:4:"size";s:6:"normal";s:8:"not null";b:0;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.content_entity_revision_id',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:26:"content_entity_revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:0;s:4:"size";s:6:"normal";s:8:"not null";b:0;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:26:"content_entity_revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:0;s:4:"size";s:6:"normal";s:8:"not null";b:0;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.content_entity_type_id',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:22:"content_entity_type_id";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:32;s:6:"binary";b:0;s:8:"not null";b:0;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:22:"content_entity_type_id";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:32;s:6:"binary";b:0;s:8:"not null";b:0;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.default_langcode',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:16:"default_langcode";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:16:"default_langcode";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.id',
  'value' => 'a:4:{s:24:"content_moderation_state";a:1:{s:6:"fields";a:1:{s:2:"id";a:4:{s:4:"type";s:6:"serial";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:2:"id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:33:"content_moderation_state_revision";a:1:{s:6:"fields";a:1:{s:2:"id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:2:"id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.langcode',
  'value' => 'a:4:{s:24:"content_moderation_state";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}s:33:"content_moderation_state_revision";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.moderation_state',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:16:"moderation_state";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:255;s:6:"binary";b:0;s:8:"not null";b:0;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:16:"moderation_state";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:255;s:6:"binary";b:0;s:8:"not null";b:0;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.revision_default',
  'value' => 'a:1:{s:33:"content_moderation_state_revision";a:1:{s:6:"fields";a:1:{s:16:"revision_default";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:0;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.revision_id',
  'value' => 'a:4:{s:24:"content_moderation_state";a:1:{s:6:"fields";a:1:{s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:0;}}}s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:33:"content_moderation_state_revision";a:1:{s:6:"fields";a:1:{s:11:"revision_id";a:4:{s:4:"type";s:6:"serial";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.revision_translation_affected',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:1:{s:6:"fields";a:1:{s:29:"revision_translation_affected";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:0;}}}s:39:"content_moderation_state_field_revision";a:1:{s:6:"fields";a:1:{s:29:"revision_translation_affected";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:0;}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.uid',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:2:{s:6:"fields";a:1:{s:3:"uid";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}}s:7:"indexes";a:1:{s:46:"content_moderation_state_field__uid__target_id";a:1:{i:0;s:3:"uid";}}}s:39:"content_moderation_state_field_revision";a:2:{s:6:"fields";a:1:{s:3:"uid";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}}s:7:"indexes";a:1:{s:46:"content_moderation_state_field__uid__target_id";a:1:{i:0;s:3:"uid";}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.uuid',
  'value' => 'a:1:{s:24:"content_moderation_state";a:2:{s:6:"fields";a:1:{s:4:"uuid";a:4:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:6:"binary";b:0;s:8:"not null";b:1;}}s:11:"unique keys";a:1:{s:43:"content_moderation_state_field__uuid__value";a:1:{i:0;s:4:"uuid";}}}}',
])
->values([
  'collection' => 'entity.storage_schema.sql',
  'name' => 'content_moderation_state.field_schema_data.workflow',
  'value' => 'a:2:{s:35:"content_moderation_state_field_data";a:2:{s:6:"fields";a:1:{s:8:"workflow";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:13:"varchar_ascii";s:6:"length";i:255;s:8:"not null";b:0;}}s:7:"indexes";a:1:{s:36:"content_moderation_state__09628d8dbc";a:1:{i:0;s:8:"workflow";}}}s:39:"content_moderation_state_field_revision";a:2:{s:6:"fields";a:1:{s:8:"workflow";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:13:"varchar_ascii";s:6:"length";i:255;s:8:"not null";b:0;}}s:7:"indexes";a:1:{s:36:"content_moderation_state__09628d8dbc";a:1:{i:0;s:8:"workflow";}}}}',
])
->execute();

$connection->merge('key_value')
->condition('collection', 'post_update')
->condition('name', 'existing_updates')
->fields([
  'collection' => 'post_update',
  'name' => 'existing_updates',
  'value' => 'a:105:{i:0;s:44:"system_post_update_claro_dropbutton_variants";i:1;s:44:"system_post_update_delete_authorize_settings";i:2;s:38:"system_post_update_delete_rss_settings";i:3;s:50:"system_post_update_enable_provider_database_driver";i:4;s:54:"system_post_update_entity_revision_metadata_bc_cleanup";i:5;s:44:"system_post_update_extra_fields_form_display";i:6;s:52:"system_post_update_remove_key_value_expire_all_index";i:7;s:37:"system_post_update_schema_version_int";i:8;s:44:"system_post_update_service_advisory_settings";i:9;s:34:"system_post_update_sort_all_config";i:10;s:35:"system_post_update_uninstall_classy";i:11;s:52:"system_post_update_uninstall_entity_reference_module";i:12;s:39:"system_post_update_uninstall_simpletest";i:13;s:35:"system_post_update_uninstall_stable";i:14;s:64:"system_post_update_recalculate_configuration_entity_dependencies";i:15;s:48:"system_post_update_add_region_to_entity_displays";i:16;s:37:"system_post_update_hashes_clear_cache";i:17;s:36:"system_post_update_timestamp_plugins";i:18;s:41:"system_post_update_classy_message_library";i:19;s:37:"system_post_update_field_type_plugins";i:20;s:48:"system_post_update_field_formatter_entity_schema";i:21;s:36:"system_post_update_fix_jquery_extend";i:22;s:40:"system_post_update_change_action_plugins";i:23;s:47:"system_post_update_change_delete_action_plugins";i:24;s:41:"system_post_update_language_item_callback";i:25;s:31:"system_post_update_extra_fields";i:26;s:37:"system_post_update_states_clear_cache";i:27;s:64:"system_post_update_add_expand_all_items_key_in_system_menu_block";i:28;s:35:"system_post_update_clear_menu_cache";i:29;s:46:"system_post_update_layout_plugin_schema_change";i:30;s:60:"system_post_update_entity_reference_autocomplete_match_limit";i:31;s:29:"user_post_update_update_roles";i:32;s:45:"user_post_update_enforce_order_of_permissions";i:33;s:62:"contact_post_update_add_message_redirect_field_to_contact_form";i:34;s:55:"search_post_update_reindex_after_diacritics_rule_change";i:35;s:29:"search_post_update_block_page";i:36;s:55:"tour_post_update_joyride_selectors_to_selector_property";i:37;s:46:"field_post_update_save_custom_storage_property";i:38;s:50:"field_post_update_entity_reference_handler_setting";i:39;s:43:"field_post_update_email_widget_size_setting";i:40;s:47:"field_post_update_remove_handler_submit_setting";i:41;s:54:"file_post_update_add_txt_if_allows_insecure_extensions";i:42;s:55:"text_post_update_add_required_summary_flag_form_display";i:43;s:42:"text_post_update_add_required_summary_flag";i:44;s:45:"block_post_update_replace_node_type_condition";i:45;s:54:"block_post_update_disable_blocks_with_missing_contexts";i:46;s:40:"block_post_update_disabled_region_update";i:47;s:42:"block_post_update_fix_negate_in_conditions";i:48;s:51:"block_content_post_update_entity_changed_constraint";i:49;s:51:"block_content_post_update_add_views_reusable_filter";i:50;s:40:"node_post_update_glossary_view_published";i:51;s:50:"node_post_update_modify_base_field_author_override";i:52;s:45:"node_post_update_rebuild_node_revision_routes";i:53;s:46:"node_post_update_configure_status_field_widget";i:54;s:41:"node_post_update_node_revision_views_data";i:55;s:56:"editor_post_update_clear_cache_for_file_reference_filter";i:56;s:39:"ckeditor5_post_update_alignment_buttons";i:57;s:40:"ckeditor5_post_update_image_toolbar_item";i:58;s:45:"comment_post_update_enable_comment_admin_view";i:59;s:42:"comment_post_update_add_ip_address_setting";i:60;s:48:"contextual_post_update_fixed_endpoint_and_markup";i:61;s:49:"dblog_post_update_convert_recent_messages_to_view";i:62;s:65:"taxonomy_post_update_clear_views_argument_validator_plugins_cache";i:63;s:43:"taxonomy_post_update_clear_views_data_cache";i:64;s:64:"taxonomy_post_update_clear_entity_bundle_field_definitions_cache";i:65;s:63:"taxonomy_post_update_handle_publishing_status_addition_in_views";i:66;s:55:"taxonomy_post_update_remove_hierarchy_from_vocabularies";i:67;s:52:"taxonomy_post_update_make_taxonomy_term_revisionable";i:68;s:50:"taxonomy_post_update_configure_status_field_widget";i:69;s:41:"image_post_update_image_loading_attribute";i:70;s:42:"image_post_update_image_style_dependencies";i:71;s:50:"image_post_update_scale_and_crop_effect_add_anchor";i:72;s:52:"views_post_update_configuration_entity_relationships";i:73;s:51:"views_post_update_field_names_for_multivalue_fields";i:74;s:33:"views_post_update_image_lazy_load";i:75;s:53:"views_post_update_provide_revision_table_relationship";i:76;s:50:"views_post_update_remove_sorting_global_text_field";i:77;s:48:"views_post_update_rename_default_display_setting";i:78;s:33:"views_post_update_sort_identifier";i:79;s:36:"views_post_update_title_translations";i:80;s:46:"views_post_update_update_cacheability_metadata";i:81;s:46:"views_post_update_cleanup_duplicate_views_data";i:82;s:46:"views_post_update_field_formatter_dependencies";i:83;s:36:"views_post_update_taxonomy_index_tid";i:84;s:41:"views_post_update_serializer_dependencies";i:85;s:39:"views_post_update_boolean_filter_values";i:86;s:33:"views_post_update_grouped_filters";i:87;s:42:"views_post_update_revision_metadata_fields";i:88;s:33:"views_post_update_entity_link_url";i:89;s:34:"views_post_update_bulk_field_moved";i:90;s:41:"views_post_update_filter_placeholder_text";i:91;s:47:"views_post_update_views_data_table_dependencies";i:92;s:45:"views_post_update_table_display_cache_max_age";i:93;s:53:"views_post_update_exposed_filter_blocks_label_display";i:94;s:48:"views_post_update_make_placeholders_translatable";i:95;s:41:"views_post_update_limit_operator_defaults";i:96;s:33:"views_post_update_remove_core_key";i:97;s:65:"menu_link_content_post_update_make_menu_link_content_revisionable";i:98;s:49:"path_post_update_create_language_content_settings";i:99;s:59:"update_post_update_add_view_update_notifications_permission";i:100;s:59:"content_moderation_post_update_update_cms_default_revisions";i:101;s:59:"content_moderation_post_update_set_default_moderation_state";i:102;s:84:"content_moderation_post_update_set_views_filter_latest_translation_affected_revision";i:103;s:58:"content_moderation_post_update_entity_display_dependencies";i:104;s:52:"content_moderation_post_update_views_field_plugin_id";}',
])
->execute();
