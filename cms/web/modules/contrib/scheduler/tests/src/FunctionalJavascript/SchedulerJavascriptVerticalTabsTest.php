<?php

namespace Drupal\Tests\scheduler\FunctionalJavascript;

/**
 * Tests the JavaScript functionality of vertical tabs summary information.
 *
 * @group scheduler_js
 */
class SchedulerJavascriptVerticalTabsTest extends SchedulerJavascriptTestBase {

  /**
   * Test editing an entity.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testEditEntitySummary($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $titleField = $this->titleField($entityTypeId);

    // Set the entity edit form to use a vertical tab for the Scheduler dates.
    $this->entityTypeObject($entityTypeId)
      ->setThirdPartySetting('scheduler', 'fields_display_mode', 'vertical_tab')
      ->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();

    // Create an entity with a scheduled publishing date.
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'publish_on' => strtotime('+2 months'),
      "$titleField" => "$entityTypeId to publish",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->pageTextContains('Scheduled for publishing');
    $assert->pageTextNotContains('Scheduled for unpublishing');
    $assert->pageTextNotContains('Not scheduled');

    // Create an entity with a scheduled unpublishing date.
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'unpublish_on' => strtotime('+3 months'),
      "$titleField" => "$entityTypeId to unpublish",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->pageTextNotContains('Scheduled for publishing');
    $assert->pageTextContains('Scheduled for unpublishing');
    $assert->pageTextNotContains('Not scheduled');

    // In Claro, Node and Product have a separate vertical "tab" block which is
    // always open. Taxonomy Term does not have vertical tabs, only the separate
    // fieldset, but this also shows the summary. Media has the old-style block
    // with side tabs, so we need to click 'Scheduling options'.
    // In Drupal 10.3 the form for editing Taxonomy Terms seemed to change, and
    // vertical tabs are implemented in a different way to 10.2. We now need to
    // click to bring focus on that tab, ready for filling the date fields.
    $page = $this->getSession()->getPage();
    if ($entityTypeId == 'media' || ($entityTypeId == 'taxonomy_term' && version_compare(\Drupal::VERSION, '10.3', '>='))) {
      $page->clickLink('Scheduling options');
    }

    // Fill in a publish_on date and check the summary text.
    $page->fillField('edit-publish-on-0-value-date', '05/02/' . (date('Y') + 1));
    $page->fillField('edit-publish-on-0-value-time', '06:00:00pm');
    $assert->waitForText('Scheduled for publishing');
    $assert->pageTextContains('Scheduled for publishing');

    // Remove both date values and check that the summary text is correct.
    // Setting the date and time values to '' only actually removes the first
    // component of each of the fields. But this is enough for drupal.behaviors
    // to update the summary correctly.
    $page->fillField('edit-publish-on-0-value-date', '');
    $page->fillField('edit-publish-on-0-value-time', '');
    $page->fillField('edit-unpublish-on-0-value-date', '');
    $page->fillField('edit-unpublish-on-0-value-time', '');
    $assert->waitForText('Not scheduled');
    $assert->pageTextNotContains('Scheduled for publishing');
    $assert->pageTextNotContains('Scheduled for unpublishing');
    $assert->pageTextContains('Not scheduled');

    // Turn off scheduled unpublishing for this entity type to verify that the
    // javascript behaviors still work as expected.
    // @see https://www.drupal.org/project/scheduler/issues/3458578
    $this->entityTypeObject($entityTypeId, $bundle)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();

    $entity = $this->createEntity($entityTypeId, $bundle, [
      'publish_on' => strtotime('+2 months'),
      "$titleField" => "$entityTypeId - not enabled for unpublishing",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->pageTextContains('Scheduled for publishing');

    // Turn on scheduled unpublishing and turn off scheduled publishing.
    $this->entityTypeObject($entityTypeId, $bundle)
      ->setThirdPartySetting('scheduler', 'publish_enable', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)->save();

    $entity = $this->createEntity($entityTypeId, $bundle, [
      'unpublish_on' => strtotime('+3 months'),
      "$titleField" => "$entityTypeId - not enabled for publishing",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->pageTextContains('Scheduled for unpublishing');
  }

  /**
   * Test configuring an entity type.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testConfigureEntityTypeSummary($entityTypeId, $bundle) {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->entityTypeObject($entityTypeId)->toUrl('edit-form'));

    $page = $this->getSession()->getPage();
    if (in_array($entityTypeId, ['node', 'media'])) {
      // For node and media bring focus to the Scheduler vertical tab.
      $page->clickLink('Scheduler');
    }
    else {
      // For taxonomy term and product, open the closed modal details block.
      $page->pressButton('Scheduler');
    }

    // Both options are enabled by default.
    $assert->pageTextContains('Publishing enabled');
    $assert->pageTextContains('Advanced options');
    $assert->pageTextContains('Unpublishing enabled');

    // Turn off the unpublishing enabled checkbox.
    $page->uncheckField('edit-scheduler-unpublish-enable');
    $this->waitForNoText('Unpublishing enabled');
    $assert->pageTextContains('Publishing enabled');
    $assert->pageTextContains('Advanced options');
    $assert->pageTextNotContains('Unpublishing enabled');

    // Turn off the publishing enabled checkbox.
    $page->uncheckField('edit-scheduler-publish-enable');
    $this->waitForNoText('Publishing enabled');
    $assert->pageTextNotContains('Publishing enabled');
    $assert->pageTextNotContains('Advanced options');

    // Turn on the publishing enabled checkbox.
    $page->checkField('edit-scheduler-publish-enable');
    $assert->waitForText('Publishing enabled');
    $assert->pageTextContains('Publishing enabled');
    $assert->pageTextNotContains('Unpublishing enabled');
    $assert->pageTextContains('Advanced options');

    // Turn on the unpublishing enabled checkbox.
    $page->checkField('edit-scheduler-unpublish-enable');
    $assert->waitForText('Unpublishing enabled');
    $assert->pageTextContains('Unpublishing enabled');

  }

}
