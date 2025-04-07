<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests that Scheduler cron has full access to the scheduled entities.
 *
 * This test uses a additional test module 'scheduler_access_test' which has a
 * custom entity access definition to deny viewing of all entities by any user
 * except user 1.
 *
 * The purpose of checking for '403' is only to demonstrate that the helper
 * module is doing its thing, it is not testing any part of the Scheduler
 * functionality. If we tested with an anonymous visitor then both the published
 * and unpublished entities would give 403 but the unpublished entity would
 * return this regardless of what the helper module was doing. Likewise if we
 * run the test with a logged in user who does not have 'view own unpublished..'
 * then the unpublished entity would give 403 regardless. However, if the user
 * does have 'view own unpublished ..' then due to the design of checkAccess()
 * within NodeAccessControlHandler this entirely takes precedence and overrides
 * any prevention of access attempted via contrib hook_node_access_records() and
 * hook_node_grants(). It is clearer in the dblog output if this test is run
 * using a logged-in user rather than the anonymous user, and hence create a new
 * user who does not have the permission 'view own unpublished {type}'.
 *
 * @group scheduler
 */
class SchedulerEntityAccessTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['scheduler_access_test'];

  /**
   * Tests Scheduler cron functionality when access to the entity is denied.
   *
   * @dataProvider dataEntityAccess
   */
  public function testEntityAccess($entityTypeId, $bundle, $field, $status) {
    $storage = $this->entityStorageObject($entityTypeId);
    // scheduler_access_test_install() sets node_access_needs_rebuild(TRUE) and
    // this works when testing the module interactively, but in a phpunit run
    // the node access table is not rebuilt. Hence do that explicitly here.
    node_access_rebuild();

    // Login as a user who is only able to view the published entities.
    $this->drupalLogin($this->drupalCreateUser());

    // Create an entity with the necessary scheduler date.
    $process = $status ? 'unpublishing' : 'publishing';
    $settings = [
      'status' => $status,
      'title' => "$entityTypeId $bundle for $process",
      $field => $this->requestTime + 1,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $settings);
    $this->drupalGet($entity->toUrl());
    // Before running cron, viewing the entity should give "403 Not Authorized"
    // regardless of whether it is published or unpublished.
    $this->assertSession()->statusCodeEquals(403);

    // Delay so that the date entered is now in the past, then run cron.
    sleep(2);
    $this->cronRun();

    // Reload the entity.
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that the entity has been published or unpublished as required.
    $this->assertTrue($entity->isPublished() === !$status, "Scheduled $process of $entityTypeId via cron.");
    // Check that the entity is still not viewable.
    $this->drupalGet($entity->toUrl());
    // After cron, viewing the entity should still give "403 Not Authorized".
    $this->assertSession()->statusCodeEquals(403);

    // Log in as admin and check that the dblog cron message is shown.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->pageTextContains($this->entityTypeObject($entityTypeId)->label() . ": scheduled $process");
  }

  /**
   * Provides data for testEntityAccess.
   *
   * @return array
   *   Each row has values: [entity type id, bundle id, field name, status].
   */
  public static function dataEntityAccess() {
    // This test is only applicable to node entity types because the other
    // entity types do not have a hook access grant system.
    // @todo Investigate how scheduler_access_test module can be expanded to
    // deny access to other entity types using a different method.
    $types = self::dataStandardEntityTypes();
    $data = [
      '#node-1' => array_merge($types['#node'], ['publish_on', FALSE]),
      '#node-2' => array_merge($types['#node'], ['unpublish_on', TRUE]),
    ];
    return $data;
  }

}
