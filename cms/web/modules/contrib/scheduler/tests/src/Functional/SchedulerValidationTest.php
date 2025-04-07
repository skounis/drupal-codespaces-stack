<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the validation when editing a node.
 *
 * @group scheduler
 */
class SchedulerValidationTest extends SchedulerBrowserTestBase {

  /**
   * Tests the validation when editing a node.
   *
   * The 'required' checks and 'dates in the past' checks are handled in other
   * tests. This test checks validation when the two fields interact, and covers
   * the error message text stored in the following constraint variables:
   *   $messageUnpublishOnRequiredIfPublishOnEntered
   *   $messageUnpublishOnRequiredIfPublishing
   *   $messageUnpublishOnTooEarly.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testValidationDuringEdit($entityTypeId, $bundle) {
    $this->drupalLogin($this->adminUser);

    // Set unpublishing to be required for this entity type.
    $this->entityTypeObject($entityTypeId)->setThirdPartySetting('scheduler', 'unpublish_required', TRUE)->save();

    // Create an unpublished entity.
    $entity = $this->createEntity($entityTypeId, $bundle, ['status' => FALSE]);

    // Edit the unpublished entity and try to save a publish-on date.
    $edit = [
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'publish_on[0][value][time]' => date('H:i:s', strtotime('+1 day', $this->requestTime)),
    ];
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    // Check that validation prevents entering a publish-on date with no
    // unpublish-on date if unpublishing is required.
    $this->assertSession()->pageTextContains("If you set a 'publish on' date then you must also set an 'unpublish on' date.");
    $this->assertSession()->pageTextNotMatches('/has been (updated|successfully saved)/');

    // Create an unpublished entity.
    $entity = $this->createEntity($entityTypeId, $bundle, ['status' => FALSE]);

    // Edit the unpublished entity and try to change the status to 'published'.
    $edit = ['status[value]' => TRUE];
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    // Check that validation prevents publishing the entity directly without an
    // unpublish-on date if unpublishing is required.
    $this->assertSession()->pageTextContains("Either you must set an 'unpublish on' date or save as unpublished.");
    $this->assertSession()->pageTextNotMatches('/has been (updated|successfully saved)/');

    // Create an unpublished entity, and try to edit and save with a publish-on
    // date later than the unpublish-on date.
    $entity = $this->createEntity($entityTypeId, $bundle, ['status' => FALSE]);
    $edit = [
      'publish_on[0][value][date]' => $this->dateFormatter->format($this->requestTime + 7200, 'custom', 'Y-m-d'),
      'publish_on[0][value][time]' => $this->dateFormatter->format($this->requestTime + 7200, 'custom', 'H:i:s'),
      'unpublish_on[0][value][date]' => $this->dateFormatter->format($this->requestTime + 1800, 'custom', 'Y-m-d'),
      'unpublish_on[0][value][time]' => $this->dateFormatter->format($this->requestTime + 1800, 'custom', 'H:i:s'),
    ];
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    // Check that validation prevents entering an unpublish-on date which is
    // earlier than the publish-on date.
    $this->assertSession()->pageTextContains("The 'unpublish on' date must be later than the 'publish on' date.");
    $this->assertSession()->pageTextNotMatches('/has been (updated|successfully saved)/');
  }

}
