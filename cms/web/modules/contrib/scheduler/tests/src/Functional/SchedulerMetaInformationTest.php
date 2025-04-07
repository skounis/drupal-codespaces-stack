<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests meta information output by scheduler.
 *
 * @group scheduler
 */
class SchedulerMetaInformationTest extends SchedulerBrowserTestBase {

  /**
   * Tests meta-information on scheduled entities.
   *
   * When an entity is scheduled for unpublication, an X-Robots-Tag HTTP header
   * is included, telling crawlers about when an item will expire and should be
   * removed from search results.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testMetaInformation($entityTypeId, $bundle) {
    // Log in.
    $this->drupalLogin($this->schedulerUser);

    // Create a published entity without scheduling dates.
    $entity = $this->createEntity($entityTypeId, $bundle, ['status' => TRUE]);

    // Since we did not set an unpublish date, there should be no X-Robots-Tag
    // header on the response.
    $this->drupalGet($entity->toUrl());
    $this->assertNull($this->getSession()->getResponseHeader('X-Robots-Tag'), 'X-Robots-Tag should not be present when no unpublish date is set.');
    // Also check that there is no meta tag.
    $this->assertSession()->responseNotContains('unavailable_after:');

    // Set an unpublish date on the entity.
    $unpublish_date = strtotime('+1 day');
    $entity->set('unpublish_on', $unpublish_date)->save();

    // The entity full page view should now have an X-Robots-Tag header with an
    // unavailable_after-directive and RFC850 date- and time-value.
    $this->drupalGet($entity->toUrl());
    $this->assertSession()->responseHeaderEquals('X-Robots-Tag', 'unavailable_after: ' . date(DATE_RFC850, $unpublish_date));

    // Check that the required meta tag is added to the html head section.
    $this->assertSession()->responseMatches('~meta name=[\'"]robots[\'"] content=[\'"]unavailable_after: ' . date(DATE_RFC850, $unpublish_date) . '[\'"]~');

    // If the entity type has a summary listing page, check that the entity is
    // shown but the two tags are not present. Only do this for node, to avoid
    // getting 404 because none of the other entity types have a summary page.
    if ($entityTypeId == 'node') {
      $this->drupalGet("$entityTypeId");
      $this->assertSession()->pageTextContains($entity->label());
      $this->assertNull($this->getSession()->getResponseHeader('X-Robots-Tag'), 'X-Robots-Tag should not be added when entity is not in "full" view mode.');
      $this->assertSession()->responseNotContains('unavailable_after:');
    }
  }

}
