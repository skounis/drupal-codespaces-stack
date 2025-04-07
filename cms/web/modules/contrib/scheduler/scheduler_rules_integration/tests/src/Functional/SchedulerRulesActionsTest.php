<?php

namespace Drupal\Tests\scheduler_rules_integration\Functional;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\scheduler\Functional\SchedulerBrowserTestBase;
use Drupal\rules\Context\ContextConfig;

/**
 * Tests the six actions that Scheduler provides for use in Rules module.
 *
 * @group scheduler_rules_integration
 */
class SchedulerRulesActionsTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['scheduler_rules_integration'];

  /**
   * The rules_reaction_rule entity object.
   *
   * @var \Drupal\rules\Entity\ReactionRuleConfig
   */
  protected $rulesStorage;

  /**
   * The rules expression plugin manager.
   *
   * @var \Drupal\rules\Engine\ExpressionManagerInterface
   */
  protected $expressionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->rulesStorage = $this->container->get('entity_type.manager')->getStorage('rules_reaction_rule');
    $this->expressionManager = $this->container->get('plugin.manager.rules_expression');
    // Login as adminUser so that we can also test the non-enabled node types.
    $this->drupalLogin($this->adminUser);

  }

  /**
   * Tests the actions which set and remove the 'Publish On' date.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testPublishOnActions($entityTypeId, $enabledBundle) {
    $nonEnabledBundle = $this->entityTypeObject($entityTypeId, 'non-enabled')->id();
    $titleField = $this->titleField($entityTypeId);
    $publish_on = $this->requestTime + 1800;
    $publish_on_formatted = $this->dateFormatter->format($publish_on, 'long');

    // The legacy rules action ids for nodes remain as:
    // -  scheduler_set_publishing_date_action
    // -  scheduler_publish_now_action
    // For all other entity types the new derived action ids are of the form:
    // -  scheduler_set_publishing_date:{type}
    // -  scheduler_publish_now:{type}
    // .
    $action_suffix = ($entityTypeId == 'node') ? '_action' : ":$entityTypeId";
    $storage = $this->entityStorageObject($entityTypeId);

    // Create rule 1 to set the publishing date.
    $rule1 = $this->expressionManager->createRule();
    $rule1->addCondition('rules_data_comparison',
        ContextConfig::create()
          ->map('data', "$entityTypeId.$titleField.value")
          ->setValue('operation', 'contains')
          ->setValue('value', 'Trigger Rule 1')
    );
    $message1 = 'RULES message 1. Action to set Publish-on date.';
    $rule1->addAction("scheduler_set_publishing_date$action_suffix",
      ContextConfig::create()
        ->map('entity', "$entityTypeId")
        ->setValue('date', $publish_on)
      )
      ->addAction('rules_system_message',
        ContextConfig::create()
          ->setValue('message', $message1)
          ->setValue('type', 'status')
    );
    // The event needs to be rules_entity_presave:{type} 'before saving' because
    // rules_entity_update:{type} 'after save' is too late to set the date.
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule1',
      'events' => [['event_name' => "rules_entity_presave:$entityTypeId"]],
      'expression' => $rule1->getConfiguration(),
    ]);
    $config_entity->save();

    // Create rule 2 to remove the publishing date and publish the entity.
    $rule2 = $this->expressionManager->createRule();
    $rule2->addCondition('rules_data_comparison',
        ContextConfig::create()
          ->map('data', "$entityTypeId.$titleField.value")
          ->setValue('operation', 'contains')
          ->setValue('value', 'Trigger Rule 2')
    );
    $message2 = 'RULES message 2. Action to remove Publish-on date and publish immediately.';
    $rule2->addAction("scheduler_remove_publishing_date$action_suffix",
      ContextConfig::create()
        ->map('entity', "$entityTypeId")
      )
      ->addAction("scheduler_publish_now$action_suffix",
        ContextConfig::create()
          ->map('entity', "$entityTypeId")
      )
      ->addAction('rules_system_message',
        ContextConfig::create()
          ->setValue('message', $message2)
          ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule2',
      'events' => [['event_name' => "rules_entity_presave:$entityTypeId"]],
      'expression' => $rule2->getConfiguration(),
    ]);
    $config_entity->save();

    $assert = $this->assertSession();

    // First, create a new scheduler-enabled entity, triggering rule 1.
    $title = "First - new enabled $enabledBundle - Trigger Rule 1";
    $this->drupalGet($this->entityAddUrl($entityTypeId, $enabledBundle));
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $assert->pageTextContains(sprintf('%s is scheduled to be published %s', $title, $publish_on_formatted));

    // Check that rule 1 is triggered and rule 2 is not. Check that a publishing
    // date has been set and the status is now unpublished.
    $assert->pageTextContains($message1);
    $assert->pageTextNotContains($message2);
    $this->assertEquals($entity->publish_on->value, $publish_on, 'Entity should be scheduled for publishing at the correct time');
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing.');
    $this->assertFalse($entity->isPublished(), 'Entity should be unpublished');

    // Second, edit a pre-existing Scheduler-enabled entity, without triggering
    // either of the rules.
    $entity = $this->createEntity($entityTypeId, $enabledBundle, [
      "$titleField" => "Second - existing enabled $enabledBundle",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit enabled $enabledBundle - but no rules will be triggered"], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that neither of the rules are triggered, no publish and unpublish
    // dates are set and the status is still published.
    $assert->pageTextNotContains($message1);
    $assert->pageTextNotContains($message2);
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing');
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing');
    $this->assertTrue($entity->isPublished(), 'Entity should remain published');

    // Edit the entity, triggering rule 1.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit enabled $enabledBundle - Trigger Rule 1"], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that rule 1 is triggered and rule 2 is not. Check that a publishing
    // date has been set and the status is now unpublished.
    $assert->pageTextContains($message1);
    $assert->pageTextNotContains($message2);
    $this->assertEquals($entity->publish_on->value, $publish_on, 'Entity should be scheduled for publishing at the correct time');
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing');
    $this->assertFalse($entity->isPublished(), 'Entity should be unpublished');

    // Edit the entity, triggering rule 2.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit enabled $enabledBundle - Trigger Rule 2"], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that rule 2 is triggered and rule 1 is not. Check that the
    // publishing date has been removed and the status is now published.
    $assert->pageTextNotContains($message1);
    $assert->pageTextContains($message2);
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing.');
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing.');
    $this->assertTrue($entity->isPublished(), 'Entity should be published.');

    // Third, create a new entity which is not scheduler-enabled.
    $title = "Third - new non-enabled $nonEnabledBundle - Trigger Rule 1";
    $this->drupalGet($this->entityAddUrl($entityTypeId, $nonEnabledBundle));
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    // Check that rule 1 issued a warning message.
    $assert->pageTextContains('warning message');
    $assert->elementExists('xpath', '//div[@aria-label="Warning message" and contains(string(), "Action")]');
    // Check that no publishing date is set.
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing');
    // Check that a log message has been recorded.
    $log = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', 'scheduler')
      ->condition('severity', RfcLogLevel::WARNING)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $log, 'There is 1 watchdog warning message from Scheduler');

    // Fourthly, edit a pre-existing entity which is not enabled for Scheduler,
    // triggering rule 1.
    $entity = $this->createEntity($entityTypeId, $nonEnabledBundle, [
      "$titleField" => "Fourth - existing non-enabled $nonEnabledBundle",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit non-enabled $nonEnabledBundle - Trigger Rule 1"], 'Save');
    // Check that rule 1 issued a warning message.
    $assert->pageTextContains('warning message');
    $assert->elementExists('xpath', '//div[@aria-label="Warning message" and contains(string(), "Action")]');
    // Check that no publishing date is set.
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing.');
    // Check that a log message has been recorded.
    $log = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', 'scheduler')
      ->condition('severity', RfcLogLevel::WARNING)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(2, $log, 'There are now 2 watchdog warning messages from Scheduler');

    // Edit the entity again, triggering rule 2.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit non-enabled $nonEnabledBundle - Trigger Rule 2"], 'Save');
    // Check that rule 2 issued a warning message.
    $assert->pageTextContains('warning message');
    $assert->elementExists('xpath', '//div[@aria-label="Warning message" and contains(string(), "Action")]');
    // Check that a second log message has been recorded.
    $log = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', 'scheduler')
      ->condition('severity', RfcLogLevel::WARNING)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(3, $log, 'There are now 3 watchdog warning messages from Scheduler');
    $this->drupalGet('admin/reports/dblog');
  }

  /**
   * Tests the actions which set and remove the 'Unpublish On' date.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testUnpublishOnActions($entityTypeId, $enabledBundle) {
    $nonEnabledBundle = $this->entityTypeObject($entityTypeId, 'non-enabled')->id();
    $titleField = $this->titleField($entityTypeId);
    $unpublish_on = $this->requestTime + 2400;
    $unpublish_on_formatted = $this->dateFormatter->format($unpublish_on, 'long');

    // The legacy rules action ids for nodes remain as:
    // -  scheduler_set_unpublishing_date_action
    // -  scheduler_unpublish_now_action
    // For all other entity types the new derived action ids are of the form:
    // -  scheduler_set_unpublishing_date:{type}
    // -  scheduler_unpublish_now:{type}
    // .
    $action_suffix = ($entityTypeId == 'node') ? '_action' : ":$entityTypeId";
    $storage = $this->entityStorageObject($entityTypeId);

    // Create rule 3 to set the unpublishing date.
    $rule3 = $this->expressionManager->createRule();
    $rule3->addCondition('rules_data_comparison',
        ContextConfig::create()
          ->map('data', "$entityTypeId.$titleField.value")
          ->setValue('operation', 'contains')
          ->setValue('value', 'Trigger Rule 3')
    );
    $message3 = 'RULES message 3. Action to set Unpublish-on date.';
    $rule3->addAction("scheduler_set_unpublishing_date$action_suffix",
      ContextConfig::create()
        ->map('entity', "$entityTypeId")
        ->setValue('date', $unpublish_on)
      )
      ->addAction('rules_system_message',
        ContextConfig::create()
          ->setValue('message', $message3)
          ->setValue('type', 'status')
    );
    // The event needs to be rules_entity_presave:{type} 'before saving' because
    // rules_entity_update:{type} 'after save' is too late to set the date.
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule3',
      'events' => [['event_name' => "rules_entity_presave:$entityTypeId"]],
      'expression' => $rule3->getConfiguration(),
    ]);
    $config_entity->save();

    // Create rule 4 to remove the unpublishing date and unpublish the entity.
    $rule4 = $this->expressionManager->createRule();
    $rule4->addCondition('rules_data_comparison',
        ContextConfig::create()
          ->map('data', "$entityTypeId.$titleField.value")
          ->setValue('operation', 'contains')
          ->setValue('value', 'Trigger Rule 4')
    );
    $message4 = "RULES message 4. Action to remove Unpublish-on date and unpublish the $entityTypeId immediately.";
    $rule4->addAction("scheduler_remove_unpublishing_date$action_suffix",
      ContextConfig::create()
        ->map('entity', "$entityTypeId")
      )
      ->addAction("scheduler_unpublish_now$action_suffix",
        ContextConfig::create()
          ->map('entity', "$entityTypeId")
      )
      ->addAction('rules_system_message',
        ContextConfig::create()
          ->setValue('message', $message4)
          ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule4',
      'events' => [['event_name' => "rules_entity_presave:$entityTypeId"]],
      'expression' => $rule4->getConfiguration(),
    ]);
    $config_entity->save();

    $assert = $this->assertSession();

    // First, create a new scheduler-enabled entity, triggering rule 3.
    $title = "First - new enabled $enabledBundle - Trigger Rule 3";
    $this->drupalGet($this->entityAddUrl($entityTypeId, $enabledBundle));
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $assert->pageTextContains(sprintf('%s is scheduled to be unpublished %s', $title, $unpublish_on_formatted));

    // Check that rule 3 is triggered and rule 4 is not. Check that a publishing
    // date has been set and the status is now unpublished.
    $assert->pageTextContains($message3);
    $assert->pageTextNotContains($message4);
    $this->assertEquals($entity->unpublish_on->value, $unpublish_on, 'Entity should be scheduled for unpublishing at the correct time');
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing.');
    $this->assertTrue($entity->isPublished(), 'Entity should be published');

    // Second, edit a pre-existing Scheduler-enabled entity, without triggering
    // either of the rules.
    $entity = $this->createEntity($entityTypeId, $enabledBundle, [
      "$titleField" => "Second - existing enabled $enabledBundle",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit enabled $enabledBundle - but no rules will be triggered"], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that neither of the rules are triggered, no publish and unpublish
    // dates are set and the status is still published.
    $assert->pageTextNotContains($message3);
    $assert->pageTextNotContains($message4);
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing');
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing');
    $this->assertTrue($entity->isPublished(), 'Entity should remain published');

    // Edit the entity, triggering rule 3.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit enabled $enabledBundle - Trigger Rule 3"], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that rule 3 is triggered and rule 4 is not. Check that an
    // unpublishing date has been set and the status is still published.
    $assert->pageTextContains($message3);
    $assert->pageTextNotContains($message4);
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing');
    $this->assertEquals($entity->unpublish_on->value, $unpublish_on, 'Entity should be scheduled for unpublishing at the correct time');
    $this->assertTrue($entity->isPublished(), 'Entity is still published');

    // Edit the entity, triggering rule 4.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit enabled $enabledBundle - Trigger Rule 4"], 'Save');
    $storage->resetCache([$entity->id()]);
    $entity = $storage->load($entity->id());
    // Check that rule 4 is triggered and rule 3 is not. Check that the
    // unpublishing date has been removed and the status is now unpublished.
    $assert->pageTextNotContains($message3);
    $assert->pageTextContains($message4);
    $this->assertEmpty($entity->publish_on->value, 'Entity should not be scheduled for publishing.');
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing.');
    $this->assertFalse($entity->isPublished(), 'Entity should be unpublished.');

    // Third, create a new entity which is not scheduler-enabled.
    $title = "Third - new non-enabled $nonEnabledBundle - Trigger Rule 3";
    $this->drupalGet($this->entityAddUrl($entityTypeId, $nonEnabledBundle));
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    // Check that rule 3 issued a warning message.
    $assert->pageTextContains('warning message');
    $assert->elementExists('xpath', '//div[@aria-label="Warning message" and contains(string(), "Action")]');
    // Check that no publishing date is set.
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing');
    // Check that a log message has been recorded.
    $log = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', 'scheduler')
      ->condition('severity', RfcLogLevel::WARNING)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $log, 'There is 1 watchdog warning message from Scheduler');

    // Fourthly, edit a pre-existing entity which is not enabled for Scheduler,
    // triggering rule 3.
    $entity = $this->createEntity($entityTypeId, $nonEnabledBundle, [
      "$titleField" => "Fourth - existing non-enabled $nonEnabledBundle",
    ]);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit non-enabled $nonEnabledBundle - Trigger Rule 3"], 'Save');
    // Check that rule 3 issued a warning message.
    $assert->pageTextContains('warning message');
    $assert->elementExists('xpath', '//div[@aria-label="Warning message" and contains(string(), "Action")]');
    // Check that no unpublishing date is set.
    $this->assertEmpty($entity->unpublish_on->value, 'Entity should not be scheduled for unpublishing.');
    // Check that a log message has been recorded.
    $log = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', 'scheduler')
      ->condition('severity', RfcLogLevel::WARNING)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(2, $log, 'There are now 2 watchdog warning messages from Scheduler');

    // Edit the entity again, triggering rule 4.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => "Edit non-enabled $nonEnabledBundle - Trigger Rule 4"], 'Save');
    // Check that rule 4 issued a warning message.
    $assert->pageTextContains('warning message');
    $assert->elementExists('xpath', '//div[@aria-label="Warning message" and contains(string(), "Action")]');
    // Check that a second log message has been recorded.
    $log = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', 'scheduler')
      ->condition('severity', RfcLogLevel::WARNING)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(3, $log, 'There are now 3 watchdog warning messages from Scheduler');
    $this->drupalGet('admin/reports/dblog');
  }

}
