<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

/**
 * Test covering manipulation of add and edit entity forms.
 *
 * @group scheduler_content_moderation_integration
 */
class FormsTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui'];

  /**
   * Tests the hook_form_alter functionality.
   *
   * @dataProvider dataFormAlter
   */
  public function testEntityFormAlter($entityTypeId, $bundle, $operation) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);
    $assert = $this->assertSession();

    if ($operation == 'add') {
      $url = "{$entityTypeId}/add/{$bundle}";
    }
    else {
      $entity = $this->createEntity($entityTypeId, $bundle, []);
      $url = "{$entityTypeId}/{$entity->id()}/edit";
    }

    // Check both state fields are shown when the entity is enabled by default.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert->ElementExists('xpath', '//select[@id = "edit-publish-state-0"]');
    $assert->ElementExists('xpath', '//select[@id = "edit-unpublish-state-0"]');

    // Check that both fields have the Scheduler Settings group as parent.
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings"]//select[@id = "edit-publish-state-0"]');
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings"]//select[@id = "edit-unpublish-state-0"]');

    // Disable scheduled publishing and check that the publish-state field is
    // now hidden.
    $entityType->setThirdPartySetting('scheduler', 'publish_enable', FALSE)->save();
    $this->drupalGet($url);
    $assert->ElementNotExists('xpath', '//select[@id = "edit-publish-state-0"]');
    $assert->ElementExists('xpath', '//select[@id = "edit-unpublish-state-0"]');

    // Re-enable scheduled publishing and disable unpublishing, and check that
    // only the unpublish-state field is hidden.
    $entityType->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();
    $this->drupalGet($url);
    $assert->ElementExists('xpath', '//select[@id = "edit-publish-state-0"]');
    $assert->ElementNotExists('xpath', '//select[@id = "edit-unpublish-state-0"]');
  }

  /**
   * Tests hook_scheduler_hide_publish/unpublish_on_field().
   *
   * Kernel testHookHideSchedulerFields() checks the various combinations of
   * values which cause the Scheduler fields to be hidden, using just the 'node'
   * version of the hook. This functional test checks that fields do actually
   * get hidden for all supported entity types.
   *
   * @dataProvider dataFormAlter
   */
  public function testHideSchedulerFields($entityTypeId, $bundle, $operation) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);

    if ($operation == 'add') {
      $url = "{$entityTypeId}/add/{$bundle}";
    }
    else {
      $entity = $this->createEntity($entityTypeId, $bundle, []);
      $url = "{$entityTypeId}/{$entity->id()}/edit";
    }

    // By default the Scheduler publish_on and unpublish_on fields are shown.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldExists('publish_state[0]');
    $this->assertSession()->FieldExists('unpublish_on[0][value][date]');
    $this->assertSession()->FieldExists('unpublish_state[0]');

    // Remove the 'archived' state so that there is no transition relating to
    // scheduled unpublishing.
    $this->workflow->getTypePlugin()->deleteState('archived');
    $this->workflow->save();

    // Check that the unpublish_on and unpublish_state fields are hidden.
    $this->drupalGet($url);
    $this->assertSession()->FieldExists('publish_state[0]');
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_state[0]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

    // Set the unpublish_state field to be hidden, and check that the results
    // are the same as above.
    $formDisplay = $this->container->get('entity_display.repository')->getFormDisplay($entityTypeId, $bundle);
    $formDisplay->removeComponent('unpublish_state')->save();
    $this->drupalGet($url);
    $this->assertSession()->FieldExists('publish_state[0]');
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_state[0]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

    // Remove the 'publish' transition so there is nothing relating to scheduled
    // publishing.
    $this->workflow->getTypePlugin()->deleteTransition('publish');
    $this->workflow->save();

    // Check that the publish_on and publish_state fields are hidden.
    $this->drupalGet($url);
    $this->assertSession()->FieldNotExists('publish_state[0]');
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_state[0]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

    // Also set the publish_state field to be hidden, and check that the results
    // are the same as above.
    $formDisplay->removeComponent('publish_state')->save();
    $this->drupalGet($url);
    $this->assertSession()->FieldNotExists('publish_state[0]');
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_state[0]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

  }

  /**
   * Tests hook_form_alter when the state fields are hidden by another module.
   *
   * Covers scheduler_content_moderation_integration_form_node_form_alter.
   *
   * @dataProvider dataFormAlter
   */
  public function testFormAlterWithDeniedAccess($entityTypeId, $bundle, $operation) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $assert = $this->assertSession();

    if ($operation == 'add') {
      $url = "{$entityTypeId}/add/{$bundle}";
    }
    else {
      $entity = $this->createEntity($entityTypeId, $bundle, []);
      $url = "{$entityTypeId}/{$entity->id()}/edit";
    }

    // Check that both state fields are shown by default.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert->FieldExists('publish_state[0]');
    $assert->FieldExists('unpublish_state[0]');

    // Install the testing module which uses hook_form_alter to deny access to
    // the two state fields.
    \Drupal::service('module_installer')->install(['scmi_testing']);

    // Set the weight of the testing module so that it executes its hooks before
    // the main module.
    module_set_weight('scmi_testing', 1);
    module_set_weight('scheduler_content_moderation_integration', 5);
    drupal_flush_all_caches();

    // Check that both state fields are now hidden.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert->FieldNotExists('publish_state[0]');
    $assert->FieldNotExists('unpublish_state[0]');
  }

  /**
   * Helper function to test if a state field is enabled or disabled.
   *
   * @param string $field
   *   The field to test, 'publish-state' or 'unpublish-state'.
   * @param bool $showing
   *   The expected status of the field. TRUE = enabled, FALSE = hidden.
   */
  public function assertStateField(string $field, bool $showing): void {
    $assert = $this->assertSession();
    // The field is enabled if the weight setting exists and is non-zero.
    $xpath = $this->assertSession()->buildXPathQuery('//input[@id=:id and not(@value="0")]', [':id' => "edit-fields-{$field}-weight"]);
    if ($showing) {
      $assert->elementExists('xpath', $xpath);
    }
    else {
      $assert->elementNotExists('xpath', $xpath);
    }
  }

  /**
   * Tests the hook_form_alter functionality for entity type forms.
   *
   * @dataProvider dataEntityTypeFormAlter
   */
  public function testEntityTypeFormAlter($entityTypeId, $bundle, $moderatable) {
    // Give adminUser the permissions to use the field_ui 'manage form display'
    // tab for the entity type being tested.
    $this->addPermissionsToUser($this->adminUser, ["administer {$entityTypeId} form display"]);
    $this->drupalLogin($this->adminUser);

    $edit_url = $this->adminUrl('bundle_edit', $entityTypeId, $bundle);
    $form_display_url = $this->adminUrl('bundle_form_display', $entityTypeId, $bundle);

    // 1. Before any editing, check both state fields.
    $this->drupalGet($form_display_url);
    $this->assertStateField('publish-state', $moderatable);
    $this->assertStateField('unpublish-state', $moderatable);

    // 2. Edit the entity type but make no changes, and check both state fields.
    $this->drupalGet($edit_url);
    $this->submitForm([], 'Save');
    $this->drupalGet($form_display_url);
    $this->assertStateField('publish-state', $moderatable);
    $this->assertStateField('unpublish-state', $moderatable);

    // 3. Turn off both scheduler settings and check both states are hidden.
    $this->drupalGet($edit_url);
    $edit = [
      'scheduler_publish_enable' => FALSE,
      'scheduler_unpublish_enable' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->drupalGet($form_display_url);
    $this->assertStateField('publish-state', FALSE);
    $this->assertStateField('unpublish-state', FALSE);

    // 4. Enable for publishing and check the publish state field is shown.
    $this->drupalGet($edit_url);
    $edit = [
      'scheduler_publish_enable' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->drupalGet($form_display_url);
    $this->assertStateField('publish-state', $moderatable);
    $this->assertStateField('unpublish-state', FALSE);

    // 5. Enable for unpublishing and check the unpublish state field is shown.
    $this->drupalGet($edit_url);
    $edit = [
      'scheduler_unpublish_enable' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->drupalGet($form_display_url);
    $this->assertStateField('publish-state', $moderatable);
    $this->assertStateField('unpublish-state', $moderatable);
  }

  /**
   * Provides test data. Each entity type is checked for add and edit.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id, operation].
   */
  public static function dataFormAlter(): array {
    $data = [];
    foreach (static::dataEntityTypes() as $key => $entity_types) {
      $data["{$key}-add"] = array_merge($entity_types, ['add']);
      $data["{$key}-edit"] = array_merge($entity_types, ['edit']);
    }
    return $data;
  }

  /**
   * Provides test data for the entity type form alter test.
   *
   * Use the standard moderatable entity types, with an added parameter of TRUE,
   * then add commerce product (which is non-moderatable) with parameter FALSE.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id, moderatable].
   */
  public static function dataEntityTypeFormAlter(): array {
    $data = [];
    foreach (static::dataEntityTypes() as $key => $entity_types) {
      $data[$key] = array_merge($entity_types, [TRUE]);
    }
    $data['#commerce_product'] = ['commerce_product', 'test_product', FALSE];
    return $data;
  }

}
