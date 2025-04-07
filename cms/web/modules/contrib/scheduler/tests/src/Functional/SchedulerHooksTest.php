<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\commerce_product\Entity\ProductType;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\NodeType;

/**
 * Tests the API hook functions of the Scheduler module.
 *
 * This class covers the eight hook functions that Scheduler provides, allowing
 * other modules to interact with editing, scheduling and processing via cron.
 *
 * @group scheduler_api
 */
class SchedulerHooksTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   *
   * @todo 'menu_ui' is in the exported node.type definition, and 'path' is in
   * the entity_form_display. Could these be removed from the config files and
   * then not needed here?
   */
  protected static $modules = ['scheduler_api_test', 'menu_ui', 'path'];

  /**
   * The web user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Load the custom node type and check that it loaded OK. These entity types
    // are enabled for Scheduler automatically because that is pre-configured
    // in the scheduler_api_test {type}.yml files.
    $customNodeName = 'scheduler_api_node_test';
    $customNodetype = NodeType::load($customNodeName);
    $this->assertNotNull($customNodetype, "Custom node type $customNodeName failed to load during setUp");

    // Load the custom media type and check that it loaded OK.
    $customMediaName = 'scheduler_api_media_test';
    $customMediatype = MediaType::load($customMediaName);
    $this->assertNotNull($customMediatype, "Custom media type $customMediaName failed to load during setUp");

    // Load the custom product type and check that it loaded OK.
    $customProductName = 'scheduler_api_product_test';
    $customProductType = ProductType::load($customProductName);
    $this->assertNotNull($customProductType, "Custom product type $customProductName failed to load during setUp");

    // Create a web user that has permission to create and edit and schedule
    // the custom entity types.
    $this->webUser = $this->drupalCreateUser([
      "create $customNodeName content",
      "edit any $customNodeName content",
      'schedule publishing of nodes',
      'view own unpublished content',
      "create $customMediaName media",
      "edit any $customMediaName media",
      'schedule publishing of media',
      'view own unpublished media',
      "create $customProductName commerce_product",
      "update any $customProductName commerce_product",
      'schedule publishing of commerce_product',
      'view own unpublished commerce_product',
      // 'administer commerce_store' is needed to see and use any store, i.e
      // cannot add a product without this. Is it a bug?
      'administer commerce_store',
    ]);
    $this->webUser->set('name', 'Wenlock the Web user')->save();
  }

  /**
   * Provides test data containing the custom entity types.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataCustomEntityTypes() {
    $data = [
      '#node' => ['node', 'scheduler_api_node_test'],
      '#media' => ['media', 'scheduler_api_media_test'],
      '#commerce_product' => ['commerce_product', 'scheduler_api_product_test'],
    ];
    return $data;
  }

  /**
   * Covers hook_scheduler_list() and hook_scheduler_{type}_list()
   *
   * These hooks allow other modules to add more entity ids into the list being
   * processed. In real scenarios, the third-party module would likely have more
   * complex data structures and/or tables from which to identify the ids to
   * add. In this test, to keep it simple, we identify entities simply by title.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testList($entityTypeId, $bundle) {
    $storage = $this->entityStorageObject($entityTypeId);
    $this->drupalLogin($this->schedulerUser);

    // Create test entities using the standard scheduler test entity types.
    // Entity 1 is not published and has no publishing date set. The test API
    // module will add this entity into the list to be published using an
    // implementation of general hook_scheduler_list() function. Entity 2 is
    // similar but will be added via the hook_scheduler_{type}_list() function.
    $entity1 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Pink $entityTypeId list publish me",
    ]);
    $entity2 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Purple $entityTypeId list publish me",
    ]);

    // Entity 3 is published and has no unpublishing date set. The test API
    // module will add this entity into the list to be unpublished.
    $entity3 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Pink $entityTypeId list unpublish me",
    ]);
    $entity4 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Purple $entityTypeId list unpublish me",
    ]);

    // Before cron, check that entity 1 and 2 are unpublished and entity 3 and 4
    // are published.
    $this->assertFalse($entity1->isPublished(), "Before cron, $entityTypeId 1 '{$entity1->label()}' should be unpublished.");
    $this->assertFalse($entity2->isPublished(), "Before cron, $entityTypeId 2 '{$entity2->label()}' should be unpublished.");
    $this->assertTrue($entity3->isPublished(), "Before cron, $entityTypeId 3 '{$entity3->label()}' should be published.");
    $this->assertTrue($entity4->isPublished(), "Before cron, $entityTypeId 4 '{$entity4->label()}' should be published.");

    // Run cron and refresh the entities.
    scheduler_cron();
    $storage->resetCache();
    for ($i = 1; $i <= 4; $i++) {
      ${"entity$i"} = $storage->load(${"entity$i"}->id());
    }

    // Check that entity 1 and 2 have been published.
    $this->assertTrue($entity1->isPublished(), "After cron, $entityTypeId 1 '{$entity1->label()}' should be published.");
    $this->assertTrue($entity2->isPublished(), "After cron, $entityTypeId 2 '{$entity2->label()}' should be published.");

    // Check that entity 3 and 4 have been unpublished.
    $this->assertFalse($entity3->isPublished(), "After cron, $entityTypeId 3 '{$entity3->label()}' should be unpublished.");
    $this->assertFalse($entity4->isPublished(), "After cron, $entityTypeId 4 '{$entity4->label()}' should be unpublished.");
  }

  /**
   * Covers hook_scheduler_list_alter() and hook_scheduler_{type}_list_alter()
   *
   * These hook allows other modules to add or remove entity ids from the list
   * to be processed.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testListAlter($entityTypeId, $bundle) {
    $storage = $this->entityStorageObject($entityTypeId);
    $this->drupalLogin($this->schedulerUser);

    // Create test entities using the standard scheduler test entity types.
    // Entity 1 is set for scheduled publishing, but will be removed by the test
    // API generic hook_scheduler_list_alter() function. Entity 2 is similar but
    // is removed via the specific hook_scheduler_{type}_list_alter() function.
    $entity1 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Pink $entityTypeId list_alter do not publish me",
      'publish_on' => strtotime('-1 day'),
    ]);
    $entity2 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Purple $entityTypeId list_alter do not publish me",
      'publish_on' => strtotime('-1 day'),
    ]);

    // Entity 3 is not published and has no publishing date set. The test module
    // generic hook_scheduler_list_alter() function will add a date and add the
    // id into the list to be published. Entity 4 is similar but the date and id
    // is added by the specific hook_scheduler_{type}_list_alter() function.
    $entity3 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Pink $entityTypeId list_alter publish me",
    ]);
    $entity4 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Purple $entityTypeId list_alter publish me",
    ]);

    // Entity 5 is set for scheduled unpublishing, but will be removed by the
    // generic hook_scheduler_list_alter() function. Entity 6 is similar but is
    // removed by the specific hook_scheduler_{type}_list_alter() function.
    $entity5 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Pink $entityTypeId list_alter do not unpublish me",
      'unpublish_on' => strtotime('-1 day'),
    ]);
    $entity6 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Purple $entityTypeId list_alter do not unpublish me",
      'unpublish_on' => strtotime('-1 day'),
    ]);

    // Entity 7 is published and has no unpublishing date set. The generic
    // hook_scheduler_list_alter() will add a date and add the id into the list
    // to be unpublished. Entity 8 is similar but the date and id will be added
    // by the specific hook_scheduler_{type}_list_alter() function.
    $entity7 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Pink $entityTypeId list_alter unpublish me",
    ]);
    $entity8 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Purple $entityTypeId list_alter unpublish me",
    ]);

    // Before cron, check entities 1-4 are unpublished and 5-8 are published.
    $this->assertFalse($entity1->isPublished(), "Before cron, $entityTypeId 1 '{$entity1->label()}' should be unpublished.");
    $this->assertFalse($entity2->isPublished(), "Before cron, $entityTypeId 2 '{$entity2->label()}' should be unpublished.");
    $this->assertFalse($entity3->isPublished(), "Before cron, $entityTypeId 3 '{$entity3->label()}' should be unpublished.");
    $this->assertFalse($entity4->isPublished(), "Before cron, $entityTypeId 4 '{$entity4->label()}' should be unpublished.");
    $this->assertTrue($entity5->isPublished(), "Before cron, $entityTypeId 5 '{$entity5->label()}' should be published.");
    $this->assertTrue($entity6->isPublished(), "Before cron, $entityTypeId 6 '{$entity6->label()}' should be published.");
    $this->assertTrue($entity7->isPublished(), "Before cron, $entityTypeId 7 '{$entity7->label()}' should be published.");
    $this->assertTrue($entity8->isPublished(), "Before cron, $entityTypeId 8 '{$entity8->label()}' should be published.");

    // Run cron and refresh the entities from storage.
    scheduler_cron();
    $storage->resetCache();
    for ($i = 1; $i <= 8; $i++) {
      ${"entity$i"} = $storage->load(${"entity$i"}->id());
    }

    // After cron, check that entities 1-2 remain unpublished, 3-4 have now
    // been published, 5-6 remain published and 7-8 have been unpublished.
    $this->assertFalse($entity1->isPublished(), "After cron, $entityTypeId 1 '{$entity1->label()}' should be unpublished.");
    $this->assertFalse($entity2->isPublished(), "After cron, $entityTypeId 2 '{$entity2->label()}' should be unpublished.");
    $this->assertTrue($entity3->isPublished(), "After cron, $entityTypeId 3 '{$entity3->label()}' should be published.");
    $this->assertTrue($entity4->isPublished(), "After cron, $entityTypeId 4 '{$entity4->label()}' should be published.");
    $this->assertTrue($entity5->isPublished(), "After cron, $entityTypeId 5 '{$entity5->label()}' should be published.");
    $this->assertTrue($entity6->isPublished(), "After cron, $entityTypeId 6 '{$entity6->label()}' should be published.");
    $this->assertFalse($entity7->isPublished(), "After cron, $entityTypeId 7 '{$entity7->label()}' should be unpublished.");
    $this->assertFalse($entity8->isPublished(), "After cron, $entityTypeId 8 '{$entity8->label()}' should be unpublished.");
  }

  /**
   * Covers hook_scheduler_{type}_publishing_allowed()
   *
   * This hook is used to deny the publishing of individual entities. The test
   * uses the customized content type which has checkboxes 'Approved for
   * publishing' and 'Approved for unpublishing'.
   *
   * @dataProvider dataCustomEntityTypes
   */
  public function testPublishingAllowed($entityTypeId, $bundle) {
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);
    $this->drupalLogin($this->webUser);

    // Check the 'approved for publishing' field is shown on the entity form.
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->assertSession()->fieldExists('edit-field-approved-publishing-value');

    // Check that the message is shown when scheduling an entity for publishing
    // which is not yet allowed to be published.
    $edit = [
      "{$titleField}[0][value]" => "Blue $entityTypeId - Set publish-on date without approval",
      'publish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'publish_on[0][value][time]' => date('H:i:s', time() + 3),
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextMatches('/is scheduled for publishing.* but will not be published until approved/');

    // Create an entity that is scheduled but not approved for publishing. Then
    // run cron for scheduler, and check that the entity is still not published.
    $entity = $this->createUnapprovedEntity($entityTypeId, $bundle, 'publish_on');
    scheduler_cron();
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertFalse($entity->isPublished(), "Unapproved '{$entity->label()}' should not be published during cron processing.");

    // Create an entity and approve it for publishing, run cron for scheduler
    // and check that the entity is published. This is a stronger test than
    // simply approving the previously used entity above, as we do not know what
    // publish state that may be in after the cron run above.
    $entity = $this->createUnapprovedEntity($entityTypeId, $bundle, 'publish_on');
    $this->approveEntity($entityTypeId, $entity->id(), 'field_approved_publishing');
    $this->assertFalse($entity->isPublished(), "New approved '{$entity->label()}' should not be initially published.");
    scheduler_cron();
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->isPublished(), "Approved '{$entity->label()}' should be published during cron processing.");

    // Turn on immediate publishing when the date is in the past and repeat
    // the tests. It is not needed to run cron jobs here.
    $bundle_field_name = $entity->getEntityType()->get('entity_keys')['bundle'];
    $entity->$bundle_field_name->entity->setThirdPartySetting('scheduler', 'publish_past_date', 'publish')->save();

    // Check that an entity can be approved and published programmatically.
    $entity = $this->createUnapprovedEntity($entityTypeId, $bundle, 'publish_on');
    $this->assertFalse($entity->isPublished(), "New unapproved '{$entity->label()}' with a date in the past should not be published immediately after saving.");
    $this->approveEntity($entityTypeId, $entity->id(), 'field_approved_publishing');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->isPublished(), "New approved '{$entity->label()}' with a date in the past should be published immediately when created programmatically.");

    // Check that an entity can be approved and published via edit form.
    $entity = $this->createUnapprovedEntity($entityTypeId, $bundle, 'publish_on');
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(['field_approved_publishing[value]' => '1'], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->isPublished(), "Approved '{$entity->label()}' with a date in the past is published immediately after saving via edit form.");
  }

  /**
   * Covers hook_scheduler_{type}_unpublishing_allowed()
   *
   * This hook is used to deny the unpublishing of individual entities. This
   * test is simpler than the test sequence for allowed publishing, because the
   * past date 'publish' option is not applicable.
   *
   * @dataProvider dataCustomEntityTypes
   */
  public function testUnpublishingAllowed($entityTypeId, $bundle) {
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);
    $this->drupalLogin($this->webUser);

    // Check the 'approved for unpublishing' field is shown on the entity form.
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->assertSession()->fieldExists('edit-field-approved-unpublishing-value');

    // Check that the message is shown when scheduling an entity for
    // unpublishing which is not yet allowed to be unpublished.
    $edit = [
      "{$titleField}[0][value]" => "Red $entityTypeId - Set unpublish-on date without approval",
      'unpublish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'unpublish_on[0][value][time]' => date('H:i:s', time() + 3),
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextMatches('/is scheduled for unpublishing.* but will not be unpublished until approved/');

    // Create an entity that is scheduled but not approved for unpublishing, run
    // cron for scheduler, and check that the entity is still published.
    $entity = $this->createUnapprovedEntity($entityTypeId, $bundle, 'unpublish_on');
    scheduler_cron();
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->isPublished(), "Unapproved '{$entity->label()}' should not be unpublished during cron processing.");

    // Create an entity and approve it for unpublishing, run cron for scheduler
    // and check that the entity is unpublished.
    $entity = $this->createUnapprovedEntity($entityTypeId, $bundle, 'unpublish_on');
    $this->approveEntity($entityTypeId, $entity->id(), 'field_approved_unpublishing');
    $this->assertTrue($entity->isPublished(), "New approved '{$entity->label()}' should initially remain published.");
    scheduler_cron();
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    $this->assertFalse($entity->isPublished(), "Approved '{$entity->label()}' should be unpublished during cron processing.");
  }

  /**
   * Creates a new entity that is not approved.
   *
   * The entity will have a publish/unpublish date in the past to make sure it
   * will be included in the next cron run.
   *
   * @param string $entityTypeId
   *   The entity type to create, 'node' or 'media'.
   * @param string $bundle
   *   The bundle to create, 'scheduler_api_test' or 'scheduler_api_media_test'.
   * @param string $date_field
   *   The Scheduler date field to set, either 'publish_on' or 'unpublish_on'.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity object.
   */
  protected function createUnapprovedEntity($entityTypeId, $bundle, $date_field) {
    $settings = [
      'title' => (($date_field == 'publish_on') ? 'Blue' : 'Red') . " $entityTypeId {$this->randomMachineName(10)}",
      'status' => ($date_field == 'unpublish_on'),
      $date_field => strtotime('-1 day'),
      'field_approved_publishing' => 0,
      'field_approved_unpublishing' => 0,
    ];
    return $this->createEntity($entityTypeId, $bundle, $settings);
  }

  /**
   * Approves an entity for publication or unpublication.
   *
   * @param string $entityTypeId
   *   The entity type to approve, 'node' or 'media'.
   * @param int $id
   *   The id of the entity to approve.
   * @param string $field_name
   *   The name of the field to set, either 'field_approved_publishing' or
   *   'field_approved_unpublishing'.
   */
  protected function approveEntity($entityTypeId, $id, $field_name) {
    $storage = $this->entityStorageObject($entityTypeId);
    $storage->resetCache([$id]);
    $entity = $storage->load($id);
    $entity->set($field_name, TRUE);
    $label_field = $entity->getEntityType()->get('entity_keys')['label'];
    $entity->set($label_field, $entity->label() . " - approved for publishing: {$entity->field_approved_publishing->value}, for unpublishing: {$entity->field_approved_unpublishing->value}")->save();
  }

  /**
   * Tests the hooks which allow hiding of scheduler input fields.
   *
   * This test covers:
   *   hook_scheduler_hide_publish_date()
   *   hook_scheduler_hide_unpublish_date()
   *   hook_scheduler_{type}_hide_publish_date()
   *   hook_scheduler_{type}_hide_unpublish_date()
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testHideDateField($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);

    // Create test entities.
    $entity1 = $this->createEntity($entityTypeId, $bundle, [
      'title' => "Red $entityTypeId will have neither field hidden",
    ]);
    $entity2 = $this->createEntity($entityTypeId, $bundle, [
      'title' => "Orange $entityTypeId will have the publish-on field hidden",
    ]);
    $entity3 = $this->createEntity($entityTypeId, $bundle, [
      'title' => "Yellow $entityTypeId will have the unpublish-on field hidden",
    ]);
    $entity4 = $this->createEntity($entityTypeId, $bundle, [
      'title' => "Green $entityTypeId will have both Scheduler fields hidden",
    ]);

    // Set the scheduler fieldset to always expand, for ease during development.
    $bundle_field_name = $entity1->getEntityType()->get('entity_keys')['bundle'];
    $entity1->$bundle_field_name->entity->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Entity 1 'Red' should have both fields displayed.
    $this->drupalGet($entity1->toUrl('edit-form'));
    $assert->ElementExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');

    // Entity 2 'Orange' should have only the publish-on field hidden.
    $this->drupalGet($entity2->toUrl('edit-form'));
    $assert->ElementNotExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');

    // Entity 3 'Yellow' should have only the unpublish-on field hidden.
    $this->drupalGet($entity3->toUrl('edit-form'));
    $assert->ElementExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementNotExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');

    // Entity 4 'Green' should have both publish-on and unpublish-on hidden.
    $this->drupalGet($entity4->toUrl('edit-form'));
    $assert->ElementNotExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementNotExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');
  }

  /**
   * Tests when other modules execute the 'publish' and 'unpublish' processes.
   *
   * This test covers:
   *   hook_scheduler_publish_process()
   *   hook_scheduler_unpublish_process()
   *   hook_scheduler_{type}_publish_process()
   *   hook_scheduler_{type}_unpublish_process()
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testPublishUnpublishProcess($entityTypeId, $bundle) {
    // $this->drupalLogin($this->schedulerUser);
    $storage = $this->entityStorageObject($entityTypeId);

    // Create test entities.
    $entity1 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Red $entityTypeId will cause a failure on publishing",
      'publish_on' => strtotime('-1 day'),
    ]);
    $entity2 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Orange $entityTypeId will be unpublished by the API test module not Scheduler",
      'unpublish_on' => strtotime('-1 day'),
    ]);
    $entity3 = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'title' => "Yellow $entityTypeId will be published by the API test module not Scheduler",
      'publish_on' => strtotime('-1 day'),
    ]);
    // 'Green' will have both fields hidden so is harder to test manually.
    // Therefore introduce a different colour - Blue.
    $entity4 = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'title' => "Blue $entityTypeId will cause a failure on unpublishing",
      'unpublish_on' => strtotime('-1 day'),
    ]);

    // Simulate a cron run.
    scheduler_cron();

    // Check red.
    $storage->resetCache([$entity1->id()]);
    $entity1 = $storage->load($entity1->id());
    $this->assertFalse($entity1->isPublished(), 'Red should remain unpublished.');
    $this->assertNotEmpty($entity1->publish_on->value, 'Red should still have a publish-on date.');

    // Check orange.
    $storage->resetCache([$entity2->id()]);
    $entity2 = $storage->load($entity2->id());
    $this->assertFalse($entity2->isPublished(), 'Orange should be unpublished.');
    $this->assertStringContainsString('unpublishing processed by API test module', $entity2->label(), 'Orange should be processed by the API test module.');
    $this->assertEmpty($entity2->unpublish_on->value, 'Orange should not have an unpublish-on date.');

    // Check yellow.
    $storage->resetCache([$entity3->id()]);
    $entity3 = $storage->load($entity3->id());
    $this->assertTrue($entity3->isPublished(), 'Yellow should be published.');
    $this->assertStringContainsString('publishing processed by API test module', $entity3->label(), 'Yellow should be processed by the API test module.');
    $this->assertEmpty($entity3->publish_on->value, 'Yellow should not have a publish-on date.');

    // Check blue.
    $storage->resetCache([$entity4->id()]);
    $entity4 = $storage->load($entity4->id());
    $this->assertTrue($entity4->isPublished(), 'Blue should remain published.');
    $this->assertNotEmpty($entity4->unpublish_on->value, 'Blue should still have an unpublish-on date.');
  }

}
