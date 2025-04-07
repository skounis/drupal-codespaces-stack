<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the default time functionality.
 *
 * The test helper module scheduler_extras is used in testDefaultWithHiddenTime.
 * To reduce complexity, and avoid having to create custom entity types, it acts
 * on the standard test entity types. Hence the module is only enabled in that
 * test, not via a protected static $modules declaration.
 *
 * @group scheduler
 */
class SchedulerDefaultTimeTest extends SchedulerBrowserTestBase {

  /**
   * The default time.
   *
   * @var string
   */
  protected $defaultTime;

  /**
   * The Publish On datetime derived using the default time.
   *
   * @var \DateTime
   */
  protected $publishTime;

  /**
   * The Unpublish On datetime derived using the default time.
   *
   * @var \DateTime
   */
  protected $unpublishTime;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // For this test we use a default time of 6:30:15am.
    $this->defaultTime = '06:30:15';
    $config = $this->config('scheduler.settings');
    $config->set('default_time', $this->defaultTime)->save();

    // Create DateTime objects to hold the two scheduling dates. This is better
    // than using raw unix timestamps because it caters for daylight-saving
    // shifts properly.
    // @see https://www.drupal.org/project/scheduler/issues/2957490
    $this->publishTime = new \DateTime();
    $this->publishTime->add(new \DateInterval('P1D'))->setTime(6, 30, 15);

    $this->unpublishTime = new \DateTime();
    $this->unpublishTime->add(new \DateInterval('P2D'))->setTime(6, 30, 15);
  }

  /**
   * Test the default time functionality during content creation and edit.
   *
   * This test covers the default scenario where the dates are optional and not
   * required. A javascript test covers the cases where the dates are required.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testDefaultTime($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);
    $config = $this->config('scheduler.settings');
    $titleField = $this->titleField($entityTypeId);

    // We cannot easily test the full validation message as they contain the
    // current time which can be one or two seconds in the past. The best we can
    // do is check the fixed part of the message as it is when passed to t() in
    // Datetime::validateDatetime. Tests only needs to work in English anyway.
    $publish_validation_message = 'The Publish on date is invalid.';
    $unpublish_validation_message = 'The Unpublish on date is invalid.';

    // First test with the "date only" functionality disabled.
    $config->set('allow_date_only', FALSE)->save();

    // Test that entering a time is required.
    $title = 'No time ' . $this->randomMachineName(8);
    $edit = [
      "{$titleField}[0][value]" => $title,
      'publish_on[0][value][date]' => $this->publishTime->format('Y-m-d'),
      'unpublish_on[0][value][date]' => $this->unpublishTime->format('Y-m-d'),
    ];
    // Create an entity and check that the expected error messages are shown.
    $add_url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($add_url);
    $this->submitForm($edit, 'Save');
    // By default it is required to enter a time when scheduling content for
    // publishing and for unpublishing.
    $this->assertSession()->pageTextNotMatches('/' . $title . ' is scheduled to be published .* and unpublished .*/');
    $this->assertSession()->pageTextContains($publish_validation_message);
    $this->assertSession()->pageTextContains($unpublish_validation_message);

    // Allow the user to enter only a date with no time.
    $config->set('allow_date_only', TRUE)->save();

    // Create an entity and check that the validation messages are not shown.
    $this->drupalGet($add_url);
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextNotContains($publish_validation_message);
    $this->assertSession()->pageTextNotContains($unpublish_validation_message);

    // Get the pattern of the 'long' default date format.
    $date_format_storage = $this->container->get('entity_type.manager')->getStorage('date_format');
    $long_pattern = $date_format_storage->load('long')->getPattern();

    // Check that the scheduled information is shown after saving and that the
    // time is correct.
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s',
      $title, $this->publishTime->format($long_pattern), $this->unpublishTime->format($long_pattern)));

    if ($entity = $this->getEntityByTitle($entityTypeId, $title)) {
      // Check that the correct scheduled dates are stored in the entity.
      $this->assertEquals($this->publishTime->getTimestamp(), (int) $entity->publish_on->value, 'The publish_on value is stored correctly.');
      $this->assertEquals($this->unpublishTime->getTimestamp(), (int) $entity->unpublish_on->value, 'The unpublish_on value is stored correctly.');

      // Check that the default time has been added to the form on edit.
      $this->drupalGet($entity->toUrl('edit-form'));
      $this->assertSession()->FieldValueEquals('publish_on[0][value][time]', $this->defaultTime);
      $this->assertSession()->FieldValueEquals('unpublish_on[0][value][time]', $this->defaultTime);
    }
    else {
      $this->fail('The expected entity was not found.');
    }
  }

  /**
   * Test that the default times are set if the form time elements are hidden.
   *
   * This test uses the 'scheduler_extras' helper module, which hides the time
   * elements of both of the scheduler date input fields.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testDefaultWithHiddenTime($entityTypeId, $bundle) {
    \Drupal::service('module_installer')->install(['scheduler_extras']);
    $titleField = $this->titleField($entityTypeId);
    $this->drupalLogin($this->schedulerUser);

    // Allow the user to enter only a date with no time.
    $this->config('scheduler.settings')->set('allow_date_only', TRUE)->save();

    // Define date values but no time values.
    $title = 'Hidden Time Elements ' . $this->randomMachineName(8);
    $edit = [
      "{$titleField}[0][value]" => $title,
      'publish_on[0][value][date]' => $this->publishTime->format('Y-m-d'),
      'unpublish_on[0][value][date]' => $this->unpublishTime->format('Y-m-d'),
    ];

    // Create an entity and check that the time fields are hidden.
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldExists('unpublish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('publish_on[0][value][time]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][time]');
    $this->submitForm($edit, 'Save');

    // Get the pattern of the 'long' default date format.
    $date_format_storage = $this->container->get('entity_type.manager')->getStorage('date_format');
    $long_pattern = $date_format_storage->load('long')->getPattern();

    // Check that the message has the correct default time after saving.
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s',
      $title, $this->publishTime->format($long_pattern), $this->unpublishTime->format($long_pattern)));

    if ($entity = $this->getEntityByTitle($entityTypeId, $title)) {
      // Check that the correct scheduled dates are stored in the node.
      $this->assertEquals($this->publishTime->getTimestamp(), (int) $entity->publish_on->value, 'The publish_on value is stored correctly.');
      $this->assertEquals($this->unpublishTime->getTimestamp(), (int) $entity->unpublish_on->value, 'The unpublish_on value is stored correctly.');
    }
    else {
      $this->fail('The expected entity was not found.');
    }
  }

}
