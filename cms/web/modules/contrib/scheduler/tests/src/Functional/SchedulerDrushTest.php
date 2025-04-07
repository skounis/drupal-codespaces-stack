<?php

namespace Drupal\Tests\scheduler\Functional;

use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the Drush commands provided by Scheduler.
 *
 * @group scheduler_drush
 */
class SchedulerDrushTest extends SchedulerBrowserTestBase {

  use DrushTestTrait;

  /**
   * Tests the messages from Scheduler Drush cron.
   */
  public function testDrushCronMessages() {
    // Run the plain command using the full scheduler:cron command name, and
    // check that all of the output messages are shown.
    $this->drush('scheduler:cron');
    $messages = $this->getErrorOutput();
    $this->assertStringContainsString('Lightweight cron run activated by drush command', $messages, 'Starting dblog message not found');
    $this->assertStringContainsString('Lightweight cron run completed', $messages, 'Ending dblog message not found');
    $this->assertStringContainsString('Message: Scheduler lightweight cron completed', $messages, 'Drush message not found');

    // Use the sch:cron alias and simulate the --nomsg parameter, then check
    // that the drush confirmation message is not shown.
    $this->drush('sch:cron', [], ['nomsg' => '1']);
    $messages = $this->getErrorOutput();
    $this->assertStringNotContainsString('Message: Scheduler lightweight cron completed', $messages, '--nomsg parameter did not work');

    // Use the alternative alias sch-cron and add the --nolog parameter, then
    // check that the dblog messages are not shown.
    $this->drush('sch-cron', [], ['nolog' => '1']);
    $messages = $this->getErrorOutput();
    $this->assertStringNotContainsString('Lightweight cron run activated by drush command', $messages, '--nolog parameter did not work for starting message');
    $this->assertStringNotContainsString('Lightweight cron run completed', $messages, '--nolog parameter did not work for ending message');
  }

  /**
   * Tests scheduled publishing and unpublishing of entities via Drush.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testDrushCronPublishing($entityTypeId, $bundle) {
    // Create an entity which is scheduled for publishing.
    $title1 = $this->randomMachineName(20) . ' for publishing';
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => $title1,
      'publish_on' => strtotime('-3 hours'),
    ]);

    // Create an entity which is scheduled for unpublishing.
    $title2 = $this->randomMachineName(20) . ' for unpublishing';
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => $title2,
      'unpublish_on' => strtotime('-2 hours'),
    ]);

    // Run Scheduler's drush cron command and check that the expected publishing
    // and unpublishing messages are found.
    $this->drush('scheduler:cron');
    $messages = $this->getErrorOutput();
    $bundle_field = $entity->getEntityType()->get('entity_keys')['bundle'];
    $type_label = $entity->$bundle_field->entity->label();
    $this->assertStringContainsString(sprintf('%s: scheduled publishing of %s', $type_label, $title1), $messages, 'Scheduled publishing message not found');
    $this->assertStringContainsString(sprintf('%s: scheduled unpublishing of %s', $type_label, $title2), $messages, 'Scheduled unpublishing message not found');
  }

  /**
   * Tests the Entity Update command.
   */
  public function testDrushEntityUpdate() {
    // This test could be expanded to check the full functionality of the
    // entityUpdate() function. But initially, just call the function to check
    // that it runs, and produces the 'nothing to update' message.
    $this->drush('scheduler:entity-update');
    $messages = $this->getErrorOutput();
    $this->assertStringContainsString('Scheduler entity update - nothing to update', $messages, 'Error! Entity update message not found');
  }

}
