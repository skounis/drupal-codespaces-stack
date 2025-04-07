<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests some permissions of the Scheduler module.
 *
 * These tests check the permissions when adding and editing a scheduler-enabled
 * node or media entity type. The permission to access the scheduled content
 * overview and user tab views is covered in SchedulerViewsAccessTest.
 *
 * @group scheduler
 */
class SchedulerPermissionsTest extends SchedulerBrowserTestBase {

  /**
   * A user who can schedule node entities.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $nodeUser;

  /**
   * A user who can schedule media entities.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $mediaUser;

  /**
   * A user who can schedule commerce_product entities.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $commerceProductUser;

  /**
   * A user who can schedule taxonomy_term entities.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $taxonomyTermUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Define a set of permissions which all users get. Then in addition, each
    // user gets the specific permission to schedule their own entity type.
    // The permission 'administer nodes' is needed when setting the node status
    // field on edit. There is no corresponding separate permission for media or
    // product entity types.
    $permissions = [
      'create ' . $this->type . ' content',
      'edit own ' . $this->type . ' content',
      'administer nodes',
      'create ' . $this->mediaTypeName . ' media',
      'edit own ' . $this->mediaTypeName . ' media',
      'view own unpublished media',
      'create ' . $this->productTypeName . ' commerce_product',
      'update own ' . $this->productTypeName . ' commerce_product',
      'view own unpublished commerce_product',
      // 'administer commerce_store' is needed to see and use any store, i.e
      // cannot add a product without this. Is it a bug?
      'administer commerce_store',
      'create terms in ' . $this->vocabularyId,
      'edit terms in ' . $this->vocabularyId,
      // There is no 'view unpublished taxonomy term' permission so instead we
      // have to use 'administer taxonomy'.
      'administer taxonomy',
    ];

    // Create a user who can add and edit the standard scheduler-enabled
    // entities, but only schedule nodes.
    $this->nodeUser = $this->drupalCreateUser(array_merge($permissions, ['schedule publishing of nodes']));
    $this->nodeUser->set('name', 'Noddy the Node Editor')->save();

    // Create a user who can add and edit the standard scheduler-enabled
    // entities, but only schedule media items.
    $this->mediaUser = $this->drupalCreateUser(array_merge($permissions, ['schedule publishing of media']));
    $this->mediaUser->set('name', 'Medina the Media Editor')->save();

    // Create a user who can add and edit the standard scheduler-enabled
    // entities, but only schedule products.
    $this->commerceProductUser = $this->drupalCreateUser(array_merge($permissions, ['schedule publishing of commerce_product']));
    $this->commerceProductUser->set('name', 'Proctor the Product Editor')->save();

    // Create a user who can add and edit the standard scheduler-enabled
    // entities, but only schedule taxonomy terms.
    $this->taxonomyTermUser = $this->drupalCreateUser(array_merge($permissions, ['schedule publishing of taxonomy_term']));
    $this->taxonomyTermUser->set('name', 'Taximayne the Taxonomy Editor')->save();
  }

  /**
   * Tests that users without permission do not see the scheduler date fields.
   *
   * @dataProvider dataPermissionsTest
   */
  public function testUserPermissionsAdd($entityTypeId, $bundle, $user) {
    $titleField = $this->titleField($entityTypeId);

    // Log in with the required user, as specified by the parameter.
    $this->drupalLogin($this->{$user});

    // Initially run tests when publishing and unpublishing are not required.
    $this->entityTypeObject($entityTypeId)->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', FALSE)
      ->save();

    // Check that the fields are displayed as expected when creating an entity.
    // If the user variable matches the entity type id (after converting the
    // entity type id from snake_case to lowerCamelCase) then that user has
    // scheduling permission on this type, so the fields should be shown.
    // Otherwise the fields should not be shown.
    $add_url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($add_url);
    $camelCaseEntityTypeId = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $entityTypeId))));
    if (strpos($user, $camelCaseEntityTypeId) !== FALSE) {
      $this->assertSession()->fieldExists('publish_on[0][value][date]');
      $this->assertSession()->fieldExists('unpublish_on[0][value][date]');
    }
    else {
      $this->assertSession()->fieldNotExists('publish_on[0][value][date]');
      $this->assertSession()->fieldNotExists('unpublish_on[0][value][date]');
    }

    // Check that the new entity can be saved and published.
    $title = 'Published - ' . $this->randomString(15);
    $edit = ["{$titleField}[0][value]" => $title, 'status[value]' => TRUE];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));
    $this->assertNotEmpty($entity = $this->getEntityByTitle($entityTypeId, $title), sprintf('The new %s with title "%s" was created successfully.', $entityTypeId, $title));
    $this->assertTrue($entity->isPublished(), 'The new entity is published');

    // Check that a new entity can be saved as unpublished.
    $title = 'Unpublished - ' . $this->randomString(15);
    $edit = ["{$titleField}[0][value]" => $title, 'status[value]' => FALSE];
    $this->drupalGet($add_url);
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextMatches($this->entitySavedMessage($entityTypeId, $title));
    $this->assertNotEmpty($entity = $this->getEntityByTitle($entityTypeId, $title), sprintf('The new %s with title "%s" was created successfully.', $entityTypeId, $title));
    $this->assertFalse($entity->isPublished(), 'The new entity is unpublished');

    // Set publishing and unpublishing to required, to make it a stronger test.
    // @todo Add tests when scheduled publishing and unpublishing are required.
    // Cannot be done until we make a decision on what 'required'  means.
    // @see https://www.drupal.org/node/2707411
    // "Conflict between 'required publishing' and not having scheduler
    // permission"
  }

  /**
   * Tests that users without permission can edit existing scheduled content.
   *
   * @dataProvider dataPermissionsTest
   */
  public function testUserPermissionsEdit($entityTypeId, $bundle, $user) {
    $storage = $this->entityStorageObject($entityTypeId);
    $titleField = $this->titleField($entityTypeId);

    // Log in with the required user, as specified by the parameter.
    $this->drupalLogin($this->{$user});

    $publish_time = strtotime('+ 6 hours', $this->requestTime);
    $unpublish_time = strtotime('+ 10 hours', $this->requestTime);

    // Create an unpublished entity with a publish_on date.
    $unpublished_entity = $this->createEntity($entityTypeId, $bundle, [
      'status' => FALSE,
      'publish_on' => $publish_time,
    ]);

    // Verify that the publish_on date is stored as expected before editing.
    $this->assertEquals($publish_time, $unpublished_entity->publish_on->value, 'The publish_on value is stored correctly before edit.');

    // Edit the unpublished entity and check that the fields are displayed as
    // expected, depending on the user.
    $this->drupalGet($unpublished_entity->toUrl('edit-form'));
    $camelCaseEntityTypeId = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $entityTypeId))));
    if (strpos($user, $camelCaseEntityTypeId) !== FALSE) {
      $this->assertSession()->fieldExists('publish_on[0][value][date]');
      $this->assertSession()->fieldExists('unpublish_on[0][value][date]');
    }
    else {
      $this->assertSession()->fieldNotExists('publish_on[0][value][date]');
      $this->assertSession()->fieldNotExists('unpublish_on[0][value][date]');
    }

    // Save the entity and check the title is updated as expected.
    $title = 'For Publishing ' . $this->randomString(10);
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $unpublished_entity = $storage->load($unpublished_entity->id());
    $this->assertEquals($title, $unpublished_entity->label(), 'The unpublished entity title has been updated correctly after edit.');

    // Test that the publish_on date is still stored and is unchanged.
    $this->assertEquals($publish_time, $unpublished_entity->publish_on->value, 'The publish_on value is still stored correctly after edit.');

    // Repeat for unpublishing. Create an entity scheduled for unpublishing.
    $published_entity = $this->createEntity($entityTypeId, $bundle, [
      'status' => TRUE,
      'unpublish_on' => $unpublish_time,
    ]);

    // Verify that the unpublish_on date is stored as expected before editing.
    $this->assertEquals($unpublish_time, $published_entity->unpublish_on->value, 'The unpublish_on value is stored correctly before edit.');

    // Edit the published entity and save.
    $title = 'For Unpublishing ' . $this->randomString(10);
    $this->drupalGet($published_entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');

    // Check the updated title, to verify that edit and save was successful.
    $published_entity = $storage->load($published_entity->id());
    $this->assertEquals($title, $published_entity->label(), 'The published entity title has been updated correctly after edit.');

    // Test that the unpublish_on date is still stored and is unchanged.
    $this->assertEquals($unpublish_time, $published_entity->unpublish_on->value, 'The unpublish_on value is still stored correctly after edit.');
  }

  /**
   * Provides data for testUserPermissionsAdd() and testUserPermissionsEdit()
   *
   * The data in dataStandardEntityTypes() is expanded to test each entity type
   * with users who only have scheduler permission on one entity type and no
   * permission for the other entity types.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id, user name].
   */
  public static function dataPermissionsTest() {
    $data = [];
    foreach (self::dataStandardEntityTypes() as $key => $values) {
      $data["$key-1"] = array_merge($values, ['nodeUser']);
      $data["$key-2"] = array_merge($values, ['mediaUser']);
      $data["$key-3"] = array_merge($values, ['commerceProductUser']);
      $data["$key-4"] = array_merge($values, ['taxonomyTermUser']);
    }
    return $data;
  }

}
