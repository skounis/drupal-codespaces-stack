<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests entity types which are not enabled for scheduling.
 *
 * @group scheduler
 */
class SchedulerNonEnabledTypeTest extends SchedulerBrowserTestBase {

  /**
   * Additional core module field_ui is required for entity form display page.
   *
   * @var array
   */
  protected static $modules = ['field_ui'];

  /**
   * Tests the publish_enable and unpublish_enable entity type settings.
   *
   * @dataProvider dataNonEnabledScenarios
   */
  public function testNonEnabledType($id, $entityTypeId, $bundle, $description, $publishing_enabled, $unpublishing_enabled) {
    // Give adminUser the permissions to use the field_ui 'manage form display'
    // tab for the entity type being tested.
    $this->addPermissionsToUser($this->adminUser, ["administer {$entityTypeId} form display"]);
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);

    // The 'default' case specifically checks the behavior of the unchanged
    // settings, so only change these when not running the default test.
    if ($description != 'Default') {
      // Set the enabled checkboxes via entity type admin form. This will also
      // partially test the form display adjustments.
      $this->drupalGet($this->adminUrl('bundle_edit', $entityTypeId, $bundle));
      $edit = [
        'scheduler_publish_enable' => $publishing_enabled,
        'scheduler_unpublish_enable' => $unpublishing_enabled,
      ];
      if ($entityTypeId == 'commerce_product') {
        // Products need an extra checkbox to be ticked.
        // cspell:ignore variationtypes .
        $edit['edit-variationtypes-default'] = TRUE;
      }
      $this->submitForm($edit, 'Save');
      // Check that the save was successful with no error message.
      $this->assertSession()->statusMessageNotExists('error');

      // Show the form display page for info.
      $this->drupalGet($this->adminUrl('bundle_form_display', $entityTypeId, $bundle));

      // ThirdPartySettings are set correctly by saving the entity type form,
      // however this does not get replicated back to $entityType here (is this
      // a bug is core test traits somewhere?). Therefore resort to setting the
      // values here too.
      $entityType->setThirdPartySetting('scheduler', 'publish_enable', $publishing_enabled)
        ->setThirdPartySetting('scheduler', 'unpublish_enable', $unpublishing_enabled)
        ->save();
    }

    // When publishing and/or unpublishing are not enabled but the 'required'
    // setting remains on, the entity must be able to be saved without a date.
    $entityType->setThirdPartySetting('scheduler', 'publish_required', !$publishing_enabled)->save();
    $entityType->setThirdPartySetting('scheduler', 'unpublish_required', !$unpublishing_enabled)->save();

    // Allow dates in the past to be valid on saving the entity, to simplify the
    // testing process.
    $entityType->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // Create a new entity via the add/bundle url, and check that the correct
    // fields are displayed on the form depending on the enabled settings.
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    if ($publishing_enabled) {
      $this->assertSession()->fieldExists('publish_on[0][value][date]');
    }
    else {
      $this->assertSession()->fieldNotExists('publish_on[0][value][date]');
    }

    if ($unpublishing_enabled) {
      $this->assertSession()->fieldExists('unpublish_on[0][value][date]');
    }
    else {
      $this->assertSession()->fieldNotExists('unpublish_on[0][value][date]');
    }

    // Fill in the title field and check that the entity can be saved OK.
    $title = $id . 'a - ' . $description;
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));

    // Create an unpublished entity with a publishing date, which mimics what
    // could be done by a third-party module, or a by-product of the entity type
    // being enabled for publishing then being disabled before it got published.
    $title = $id . 'b - ' . $description;
    $values = [
      "$titleField" => $title,
      'status' => FALSE,
      'publish_on' => $this->requestTime - 120,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $values);

    // Check that the entity can be edited and saved OK.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));

    // Run cron and display the dblog.
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check if the entity has been published or remains unpublished.
    if ($publishing_enabled) {
      $this->assertTrue($entity->isPublished(), "The unpublished entity '$title' should now be published");
    }
    else {
      $this->assertFalse($entity->isPublished(), "The unpublished entity '$title' should remain unpublished");
    }

    // Do the same for unpublishing - create a published entity with an
    // unpublishing date in the future, to be valid for editing and saving.
    $title = $id . 'c - ' . $description;
    $values = [
      "$titleField" => $title,
      'status' => TRUE,
      'unpublish_on' => $this->requestTime + 180,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $values);

    // Check that the entity can be edited and saved.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));

    // Create a published entity with a date in the past, then run cron.
    $title = $id . 'd - ' . $description;
    $values = [
      "$titleField" => $title,
      'status' => TRUE,
      'unpublish_on' => $this->requestTime - 120,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $values);
    $this->cronRun();
    $this->drupalGet('admin/reports/dblog');

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check if the entity has been unpublished or remains published.
    if ($unpublishing_enabled) {
      $this->assertFalse($entity->isPublished(), "The published entity '$title' should now be unpublished");
    }
    else {
      $this->assertTrue($entity->isPublished(), "The published entity '$title' should remain published");
    }

    // Display the full content list and the scheduled list. Calls to these
    // pages are for information and debug only.
    $this->drupalGet($this->adminUrl('collection', $entityTypeId, $bundle));
    $this->drupalGet($this->adminUrl('scheduled', $entityTypeId, $bundle));
  }

  /**
   * Provides data for testNonEnabledType().
   *
   * @return array
   *   Each item in the test data array has the follow elements:
   *     id                     - (int) a sequential id for use in titles
   *     entityTypeId           - (string) 'node', 'media' or 'commerce_product'
   *     bundle                 - (string) the bundle which is not enabled
   *     description            - (string) describing the scenario being checked
   *     publishing_enabled     - (bool) whether publishing is enabled
   *     unpublishing_enabled   - (bool) whether unpublishing is enabled
   */
  public static function dataNonEnabledScenarios() {
    $data = [];
    foreach (self::dataNonEnabledTypes() as $key => $values) {
      $entityTypeId = $values[0];
      $bundle = $values[1];
      // By default check that the scheduler date fields are not displayed.
      $data["$key-1"] = [1, $entityTypeId, $bundle, 'Default', FALSE, FALSE];

      // Explicitly disable this content type for both settings.
      $data["$key-2"] = [2, $entityTypeId, $bundle, 'Disabling both settings', FALSE, FALSE];

      // Turn on scheduled publishing only.
      $data["$key-3"] = [3, $entityTypeId, $bundle, 'Enabling publishing only', TRUE, FALSE];

      // Turn on scheduled unpublishing only.
      $data["$key-4"] = [4, $entityTypeId, $bundle, 'Enabling unpublishing only', FALSE, TRUE];

      // For completeness turn on both scheduled publishing and unpublishing.
      $data["$key-5"] = [5, $entityTypeId, $bundle, 'Enabling both publishing and unpublishing', TRUE, TRUE];
    }
    return $data;
  }

}
