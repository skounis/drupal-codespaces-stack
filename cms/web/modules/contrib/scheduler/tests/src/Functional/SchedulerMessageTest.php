<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the 'show confirmation message' entity type setting.
 *
 * @group scheduler
 */
class SchedulerMessageTest extends SchedulerBrowserTestBase {

  /**
   * Tests the option to display or not display the confirmation message.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testConfirmationMessage($entityTypeId, $bundle) {
    // The schedulerUser is adequate for node, media and commerce_product. But
    // for taxonomy_term after editing and saving an unpublished term, the url
    // taxonomy/term/N gives 403. There is no 'view unpublished taxonomy term'
    // permission to grant to the ordinary user, therefore we login as adminUser
    // because 'administer taxonomy' allows viewing the unpublished terms.
    $entityTypeId == 'taxonomy_term' ? $this->drupalLogin($this->adminUser) : $this->drupalLogin($this->schedulerUser);
    $titleField = $this->titleField($entityTypeId);

    $publish_on = strtotime('+ 1 day 5 hours');
    $unpublish_on = strtotime('+ 2 day 7 hours');
    $publish_on_formatted = $this->dateFormatter->format($publish_on, 'long');
    $unpublish_on_formatted = $this->dateFormatter->format($unpublish_on, 'long');
    $title1 = 'Test 1 - ' . $this->randomString(10);
    $title2 = 'Test 2 - ' . $this->randomString(10);
    $title3 = 'Test 3 - ' . $this->randomString(10);

    // Create the content and check that the messages are shown by default.
    // First just a publish_on date.
    $edit = [
      "{$titleField}[0][value]" => $title1,
      'publish_on[0][value][date]' => date('Y-m-d', $publish_on),
      'publish_on[0][value][time]' => date('H:i:s', $publish_on),
    ];
    $add_url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($add_url);
    $this->submitForm($edit, 'Save');
    $entity1 = $this->getEntityByTitle($entityTypeId, $title1);
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s', $title1, $publish_on_formatted));

    // Second, just an unpublish_on date.
    $edit = [
      "{$titleField}[0][value]" => $title2,
      'unpublish_on[0][value][date]' => date('Y-m-d', $unpublish_on),
      'unpublish_on[0][value][time]' => date('H:i:s', $unpublish_on),
    ];
    $this->drupalGet($add_url);
    $this->submitForm($edit, 'Save');
    $entity2 = $this->getEntityByTitle($entityTypeId, $title2);
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be unpublished %s', $title2, $unpublish_on_formatted));

    // Third, with both dates.
    $edit = [
      "{$titleField}[0][value]" => $title3,
      'publish_on[0][value][date]' => date('Y-m-d', $publish_on),
      'publish_on[0][value][time]' => date('H:i:s', $publish_on),
      'unpublish_on[0][value][date]' => date('Y-m-d', $unpublish_on),
      'unpublish_on[0][value][time]' => date('H:i:s', $unpublish_on),
    ];
    $this->drupalGet($add_url);
    $this->submitForm($edit, 'Save');
    $entity3 = $this->getEntityByTitle($entityTypeId, $title3);
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s', $title3, $publish_on_formatted, $unpublish_on_formatted));

    // Change the option to not display the messages.
    $this->entityTypeObject($entityTypeId, $bundle)->setThirdPartySetting('scheduler', 'show_message_after_update', FALSE)->save();
    $this->drupalGet($entity1->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextNotContains('is scheduled to be published');
    $this->drupalGet($entity2->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextNotContains('is scheduled to be unpublished');
    $this->drupalGet($entity3->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextNotContains('is scheduled to be published');

    // Set back to display the messages, and check after edit.
    $this->entityTypeObject($entityTypeId, $bundle)->setThirdPartySetting('scheduler', 'show_message_after_update', TRUE)->save();
    $this->drupalGet($entity1->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s', $title1, $publish_on_formatted));
    $this->drupalGet($entity2->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be unpublished %s', $title2, $unpublish_on_formatted));
    $this->drupalGet($entity3->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s and unpublished %s', $title3, $publish_on_formatted, $unpublish_on_formatted));
  }

}
