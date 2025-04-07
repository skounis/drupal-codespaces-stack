<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

/**
 * Test covering the TransitionAccessConstraintValidator.
 *
 * @coversDefaultClass \Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint\TransitionAccessConstraintValidator
 *
 * @group scheduler_content_moderation_integration
 */
class TransitionAccessTest extends SchedulerContentModerationBrowserTestBase {

  /**
   * Test TransitionAccessConstraintValidator.
   *
   * @dataProvider dataEntityTypes
   */
  public function testTransitionAccess($entityTypeId, $bundle) {
    $this->drupalLogin($entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser);
    $titleField = ($entityTypeId == 'media') ? 'name' : 'title';

    // Create an entity and publish it using the "publish" transition.
    $title = $this->randomString();
    $edit = [
      "{$titleField}[0][value]" => $title,
      'moderation_state[0][state]' => 'published',
    ];
    $this->drupalGet("$entityTypeId/add/$bundle");
    $this->submitForm($edit, 'Save');

    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $publish_time = strtotime('+2 days');

    // Change entity moderation state to "archived" (using the "archive"
    // transition), and schedule publishing.
    $edit = [
      'moderation_state[0][state]' => 'archived',
      'publish_on[0][value][date]' => date('Y-m-d', $publish_time),
      'publish_on[0][value][time]' => date('H:i:s', $publish_time),
      'publish_state[0]' => 'published',
    ];
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->submitForm($edit, 'Save');
    // It should fail because the user does not have access to the
    // "archived_published" transition.
    $this->assertSession()->pageTextContains('You do not have access to transition from Archived to Published');

    // Ensure that allowed transitions can still be used (the "publish" one).
    $edit = [
      'moderation_state[0][state]' => 'draft',
      'publish_on[0][value][date]' => date('Y-m-d', $publish_time),
      'publish_on[0][value][time]' => date('H:i:s', $publish_time),
      'publish_state[0]' => 'published',
    ];
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->submitForm($edit, 'Save');
    $date_formatter = \Drupal::service('date.formatter');
    $this->assertSession()->pageTextContains(sprintf('%s is scheduled to be published %s.', $entity->label(), $date_formatter->format($publish_time, 'long')));
  }

  /**
   * Test access to scheduled content for users without right to transition.
   *
   * @dataProvider dataEntityTypes
   */
  public function testRestrictedTransitionAccess($entityTypeId, $bundle) {
    $schedulerUser = $entityTypeId == 'media' ? $this->schedulerMediaUser : $this->schedulerUser;
    $restrictedUser = $entityTypeId == 'media' ? $this->restrictedMediaUser : $this->restrictedUser;

    // Create a draft as restricted user.
    $titleField = ($entityTypeId == 'media') ? 'name' : 'title';
    $this->drupalLogin($restrictedUser);
    $title = $this->randomString();
    $edit = [
      "{$titleField}[0][value]" => $title,
      'moderation_state[0][state]' => 'draft',
    ];
    $this->drupalGet("$entityTypeId/add/$bundle");
    $this->submitForm($edit, 'Save');

    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $publish_time = strtotime('+2 days');
    $date_formatter = \Drupal::service('date.formatter');

    // Schedule publishing.
    $this->drupalLogin($schedulerUser);
    $edit = [
      'moderation_state[0][state]' => 'draft',
      'publish_on[0][value][date]' => date('Y-m-d', $publish_time),
      'publish_on[0][value][time]' => date('H:i:s', $publish_time),
      'publish_state[0]' => 'published',
    ];
    // Scheduler user should be able to edit the entity.
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm($edit, 'Save');

    $this->assertSession()
      ->pageTextContains(sprintf('%s is scheduled to be published %s.', $entity->label(), $date_formatter->format($publish_time, 'long')));

    // Restricted user does not have permission to scheduled transition,
    // editing access should be denied.
    $this->drupalLogin($restrictedUser);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);

    // Remove scheduling info.
    $this->drupalLogin($schedulerUser);
    $edit = [
      'moderation_state[0][state]' => 'draft',
      'publish_on[0][value][date]' => NULL,
      'publish_on[0][value][time]' => NULL,
    ];
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->submitForm($edit, 'Save');

    // Check that entity is editable when there is no scheduling
    // (using 'create_new_draft' transition).
    $this->drupalLogin($restrictedUser);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s has been updated.', $entity->label()));

    // Repeat the above for scheduled unpublishing.
    $this->drupalLogin($schedulerUser);
    $edit = [
      'moderation_state[0][state]' => 'published',
      'unpublish_on[0][value][date]' => date('Y-m-d', $publish_time),
      'unpublish_on[0][value][time]' => date('H:i:s', $publish_time),
      'unpublish_state[0]' => 'archived',
    ];
    // Scheduler user should be able to edit the entity.
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm($edit, 'Save');

    $this->assertSession()
      ->pageTextContains(sprintf('%s is scheduled to be unpublished %s.', $entity->label(), $date_formatter->format($publish_time, 'long')));

    // Restricted user does not have permission to scheduled transition,
    // editing access should be denied.
    $this->drupalLogin($restrictedUser);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);

    // Remove scheduling info.
    $this->drupalLogin($schedulerUser);
    $edit = [
      'unpublish_on[0][value][date]' => NULL,
      'unpublish_on[0][value][time]' => NULL,
    ];
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->submitForm($edit, 'Save');

    // Check entity is editable by restricted user when there is no scheduling.
    $this->drupalLogin($restrictedUser);
    $this->drupalGet("$entityTypeId/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains(sprintf('%s has been updated.', $entity->label()));
  }

}
