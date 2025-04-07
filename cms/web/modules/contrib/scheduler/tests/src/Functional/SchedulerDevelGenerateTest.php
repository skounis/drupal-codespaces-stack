<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the Scheduler interaction with Devel Generate module.
 *
 * @group scheduler
 */
class SchedulerDevelGenerateTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['devel_generate'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add Devel Generate permission to the admin user.
    $this->addPermissionsToUser($this->adminUser, [
      'administer devel_generate',
    ]);

  }

  /**
   * Helper function to count scheduled entities and assert the expected number.
   *
   * @param string $type
   *   The machine-name for the entity type to be checked.
   * @param string $bundle_field
   *   The name of the field which contains the bundle.
   * @param string $bundle
   *   The machine-name for the bundle/content type to be checked.
   * @param string $scheduler_field
   *   The field name to count, either 'publish_on' or 'unpublish_on'.
   * @param int $num_total
   *   The total number of entities that should exist.
   * @param int $num_scheduled
   *   The number of entities which should have a value in $scheduler_field.
   * @param int $time_range
   *   Optional time range from the devel form. The generated scheduler dates
   *   should be in a range of +/- this value from the current time.
   */
  protected function countScheduledEntities($type, $bundle_field, $bundle, $scheduler_field, $num_total, $num_scheduled, $time_range = NULL) {
    $storage = $this->entityStorageObject($type);

    // Check that the expected number of entities have been created.
    $count = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($bundle_field, $bundle)
      ->count()
      ->execute();
    $this->assertEquals($num_total, $count, sprintf('The expected number of %s %s is %s, found %s', $bundle, $type, $num_total, $count));

    // Check that the expected number of entities have been scheduled.
    $count = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($bundle_field, $bundle)
      ->exists($scheduler_field)
      ->count()
      ->execute();
    $this->assertEquals($num_scheduled, $count, sprintf('The expected number of %s %s with scheduled %s is %s, found %s', $bundle, $type, $scheduler_field, $num_total, $count));

    if (isset($time_range) && $num_scheduled > 0) {
      // Define the minimum and maximum times that we expect the scheduled dates
      // to be within. REQUEST_TIME remains static for the duration of this test
      // but even though devel_generate also uses uses REQUEST_TIME this will
      // slowly creep forward during successive calls. Tests can fail
      // incorrectly for this reason, hence the best approximation is to use
      // time() when calculating the upper end of the range.
      $min = $this->requestTime - $time_range;
      $max = time() + $time_range;

      $query = $storage->getAggregateQuery();
      $result = $query
        ->accessCheck(FALSE)
        ->condition($bundle_field, $bundle)
        ->aggregate($scheduler_field, 'min')
        ->aggregate($scheduler_field, 'max')
        ->execute();
      $min_found = $result[0]["{$scheduler_field}_min"];
      $max_found = $result[0]["{$scheduler_field}_max"];

      // Assert that the found values are within the expected range.
      $this->assertGreaterThanOrEqual($min, $min_found, sprintf('The minimum value found for %s is %s, earlier than the expected %s', $scheduler_field, $this->dateFormatter->format($min_found, 'custom', 'j M, H:i:s'), $this->dateFormatter->format($min, 'custom', 'j M, H:i:s')));
      $this->assertLessThanOrEqual($max, $max_found, sprintf('The maximum value found for %s is %s, later than the expected %s', $scheduler_field, $this->dateFormatter->format($max_found, 'custom', 'j M, H:i:s'), $this->dateFormatter->format($max, 'custom', 'j M, H:i:s')));
    }
  }

  /**
   * Test the functionality that Scheduler adds during entity generation.
   *
   * @dataProvider dataDevelGenerate
   */
  public function testDevelGenerate($entityTypeId, $enabled) {
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $enabled ? NULL : 'non-enabled');
    $bundle = $entityType->id();
    $bundle_field = $this->container->get('entity_type.manager')
      ->getDefinition($entityTypeId)->get('entity_keys')['bundle'];

    // Use just the minimum settings that are required, to see what happens when
    // everything else is left as default. The devel_generate form has a
    // selection list of vocabularies when generating terms but has a table of
    // checkboxes to chose which node and media types to generate.
    if ($entityTypeId == 'taxonomy_term') {
      $entity_selection = ['vids[]' => ["$bundle" => "$bundle"]];
    }
    else {
      $entity_selection = ["{$entityTypeId}_types[$bundle]" => TRUE];
    }
    $this->drupalGet($this->adminUrl('generate', $entityTypeId, $bundle));
    $this->submitForm($entity_selection, 'Generate');

    // Display the full content list and the scheduled list for the entity type
    // being generated. Calls to these pages are for information and debug only.
    // The default number of entities to create varies across the different
    // devel_generate plugins, therefore we do not count any on this first run.
    $this->drupalGet($this->adminUrl('collection', $entityTypeId, $bundle));
    $this->drupalGet($this->adminUrl('scheduled', $entityTypeId, $bundle));

    // Delete all content for this type and generate new content with only
    // publish-on dates. Use 100% as this is how we can count the expected
    // number of scheduled entities. The time range of 3600 is one hour.
    // The number of entities has to be lower than 50 until the Devel issue with
    // undefined index 'users' is available and we switch to using 8.x-3.0
    // See https://www.drupal.org/project/devel/issues/3076613
    $generate_settings = $entity_selection + [
      'num' => 40,
      'kill' => TRUE,
      'time_range' => 3600,
      'scheduler_publishing' => 100,
      'scheduler_unpublishing' => 0,
    ];
    $this->drupalGet($this->adminUrl('generate', $entityTypeId, $bundle));
    $this->submitForm($generate_settings, 'Generate');
    // Display the full content list and the scheduled content list.
    $this->drupalGet($this->adminUrl('collection', $entityTypeId, $bundle));
    $this->drupalGet($this->adminUrl('scheduled', $entityTypeId, $bundle));

    // Check we have the expected number of entities scheduled for publishing
    // only, and verify that that the dates are within the time range specified.
    $this->countScheduledEntities($entityTypeId, $bundle_field, $bundle, 'publish_on', 40, $enabled ? 40 : 0, $generate_settings['time_range']);
    $this->countScheduledEntities($entityTypeId, $bundle_field, $bundle, 'unpublish_on', 40, 0);

    // Do similar for unpublish_on date. Delete all then generate new content
    // with only unpublish-on dates. Time range 86400 is one day.
    $generate_settings = $entity_selection + [
      'num' => 30,
      'kill' => TRUE,
      'time_range' => 86400,
      'scheduler_publishing' => 0,
      'scheduler_unpublishing' => 100,
    ];
    $this->drupalGet($this->adminUrl('generate', $entityTypeId, $bundle));
    $this->submitForm($generate_settings, 'Generate');
    // Display the full content list and the scheduled content list.
    $this->drupalGet($this->adminUrl('collection', $entityTypeId, $bundle));
    $this->drupalGet($this->adminUrl('scheduled', $entityTypeId, $bundle));

    // Check we have the expected number of entities scheduled for unpublishing
    // only, and verify that that the dates are within the time range specified.
    $this->countScheduledEntities($entityTypeId, $bundle_field, $bundle, 'publish_on', 30, 0);
    $this->countScheduledEntities($entityTypeId, $bundle_field, $bundle, 'unpublish_on', 30, $enabled ? 30 : 0, $generate_settings['time_range']);

  }

  /**
   * Provides data for testDevelGenerate().
   *
   * @return array
   *   Each array item has the values:
   *     [entity type id, enable for Scheduler TRUE/FALSE].
   */
  public static function dataDevelGenerate() {
    $types = self::dataStandardEntityTypes();
    // Remove commerce_product, because Devel Generate does not cover products.
    unset($types['#commerce_product']);
    $data = [];
    // For each entity type, add a row for enabled TRUE and enabled FALSE.
    foreach ($types as $key => $values) {
      $data["$key-1"] = [$values[0], TRUE];
      $data["$key-2"] = [$values[0], FALSE];
    }
    return $data;
  }

}
