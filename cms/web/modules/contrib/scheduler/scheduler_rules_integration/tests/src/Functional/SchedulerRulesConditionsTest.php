<?php

namespace Drupal\Tests\scheduler_rules_integration\Functional;

use Drupal\Tests\scheduler\Functional\SchedulerBrowserTestBase;
use Drupal\rules\Context\ContextConfig;

/**
 * Tests the four conditions that Scheduler provides for use in Rules module.
 *
 * @group scheduler_rules_integration
 */
class SchedulerRulesConditionsTest extends SchedulerBrowserTestBase {

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
  }

  /**
   * Tests the conditions for whether an entity type is enabled for Scheduler.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testEntityTypeEnabledConditions($entityTypeId, $bundle) {

    // The legacy rules condition ids for nodes remain as:
    // -  scheduler_condition_publishing_is_enabled
    // -  scheduler_condition_unpublishing_is_enabled
    // For all other entity types the new derived condition ids are of the form:
    // -  scheduler_publishing_is_enabled:{type}
    // -  scheduler_unpublishing_is_enabled:{type}
    // .
    $condition_prefix = ($entityTypeId == 'node') ? 'scheduler_condition_' : 'scheduler_';
    $condition_suffix = ($entityTypeId == 'node') ? '' : ":$entityTypeId";
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);
    $assert = $this->assertSession();

    // Create a reaction rule to display a message when viewing an entity of a
    // type that is enabled for scheduled publishing.
    // "viewing content" actually means "viewing PUBLISHED content".
    $rule1 = $this->expressionManager->createRule();
    $rule1->addCondition("{$condition_prefix}publishing_is_enabled{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")
    );
    $message1 = 'RULES message 1. This entity type is enabled for scheduled publishing.';
    $rule1->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message1)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule1',
      'events' => [['event_name' => "rules_entity_view:$entityTypeId"]],
      'expression' => $rule1->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when viewing an entity of a
    // type that is enabled for scheduled unpublishing.
    $rule2 = $this->expressionManager->createRule();
    $rule2->addCondition("{$condition_prefix}unpublishing_is_enabled{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")
    );
    $message2 = 'RULES message 2. This entity type is enabled for scheduled unpublishing.';
    $rule2->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message2)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule2',
      'events' => [['event_name' => "rules_entity_view:$entityTypeId"]],
      'expression' => $rule2->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when viewing an entity of a
    // type that is NOT enabled for scheduled publishing.
    $rule3 = $this->expressionManager->createRule();
    $rule3->addCondition("{$condition_prefix}publishing_is_enabled{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")->negateResult()
    );
    $message3 = 'RULES message 3. This entity type is not enabled for scheduled publishing.';
    $rule3->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message3)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule3',
      'events' => [['event_name' => "rules_entity_view:$entityTypeId"]],
      'expression' => $rule3->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when viewing an entity of a
    // type that is NOT enabled for scheduled unpublishing.
    $rule4 = $this->expressionManager->createRule();
    $rule4->addCondition("{$condition_prefix}unpublishing_is_enabled{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")->negateResult()
    );
    $message4 = 'RULES message 4. This entity type is not enabled for scheduled unpublishing.';
    $rule4->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message4)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule4',
      'events' => [['event_name' => "rules_entity_view:$entityTypeId"]],
      'expression' => $rule4->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a published entity.
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => "Enabled Conditions - $entityTypeId $bundle",
      'status' => TRUE,
    ]);

    // View the entity and check the default position - that the entity type is
    // enabled for both publishing and unpublishing.
    $this->drupalGet($entity->toUrl());
    $assert->pageTextContains($message1);
    $assert->pageTextContains($message2);
    $assert->pageTextNotContains($message3);
    $assert->pageTextNotContains($message4);

    // Turn off scheduled publishing for the entity type and check the rules.
    $entityType->setThirdPartySetting('scheduler', 'publish_enable', FALSE)->save();
    drupal_flush_all_caches();
    $this->drupalGet($entity->toUrl());
    $assert->pageTextNotContains($message1);
    $assert->pageTextContains($message2);
    $assert->pageTextContains($message3);
    $assert->pageTextNotContains($message4);

    // Turn off scheduled unpublishing for the entity type and the check again.
    $entityType->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();
    drupal_flush_all_caches();
    $this->drupalGet($entity->toUrl());
    $assert->pageTextNotContains($message1);
    $assert->pageTextNotContains($message2);
    $assert->pageTextContains($message3);
    $assert->pageTextContains($message4);

  }

  /**
   * Tests the conditions for whether an entity is scheduled.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testEntityIsScheduledConditions($entityTypeId, $bundle) {
    // The legacy rules condition ids for nodes remain as:
    // -  scheduler_condition_node_scheduled_for_publishing
    // -  scheduler_condition_node_scheduled_for_unpublishing
    // For all other entity types the new derived condition ids are of the form:
    // -  scheduler_entity_is_scheduled_for_publishing:{type}
    // -  scheduler_entity_is_scheduled_for_unpublishing:{type}
    // .
    $condition_prefix = ($entityTypeId == 'node') ? 'scheduler_condition_node_' : 'scheduler_entity_is_';
    $condition_suffix = ($entityTypeId == 'node') ? '' : ":$entityTypeId";
    $assert = $this->assertSession();

    // Create a reaction rule to display a message when an entity is updated and
    // is not scheduled for publishing.
    $rule5 = $this->expressionManager->createRule();
    $rule5->addCondition("{$condition_prefix}scheduled_for_publishing{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")->negateResult()
    );
    $message5 = "RULES message 5. This $entityTypeId is not scheduled for publishing.";
    $rule5->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message5)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule5',
      'events' => [['event_name' => "rules_entity_update:$entityTypeId"]],
      'expression' => $rule5->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when an entity is updated and
    // is not scheduled for unpublishing.
    $rule6 = $this->expressionManager->createRule();
    $rule6->addCondition("{$condition_prefix}scheduled_for_unpublishing{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")->negateResult()
    );
    $message6 = "RULES message 6. This $entityTypeId is not scheduled for unpublishing.";
    $rule6->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message6)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule6',
      'events' => [['event_name' => "rules_entity_update:$entityTypeId"]],
      'expression' => $rule6->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when an entity is updated and
    // is scheduled for publishing.
    $rule7 = $this->expressionManager->createRule();
    $rule7->addCondition("{$condition_prefix}scheduled_for_publishing{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")
    );
    $message7 = "RULES message 7. This $entityTypeId is scheduled for publishing.";
    $rule7->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message7)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule7',
      'events' => [['event_name' => "rules_entity_update:$entityTypeId"]],
      'expression' => $rule7->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a reaction rule to display a message when an entity is updated and
    // is scheduled for unpublishing.
    $rule8 = $this->expressionManager->createRule();
    $rule8->addCondition("{$condition_prefix}scheduled_for_unpublishing{$condition_suffix}",
      ContextConfig::create()->map('entity', "$entityTypeId")
    );
    $message8 = "RULES message 8. This $entityTypeId is scheduled for unpublishing.";
    $rule8->addAction('rules_system_message', ContextConfig::create()
      ->setValue('message', $message8)
      ->setValue('type', 'status')
      );
    $config_entity = $this->rulesStorage->create([
      'id' => 'rule8',
      'events' => [['event_name' => "rules_entity_update:$entityTypeId"]],
      'expression' => $rule8->getConfiguration(),
    ]);
    $config_entity->save();

    $this->drupalLogin($this->schedulerUser);

    // Create a published entity.
    $entity = $this->createEntity($entityTypeId, $bundle, [
      'title' => "Scheduled Conditions - $entityTypeId $bundle",
      'uid' => $this->schedulerUser->id(),
      'status' => TRUE,
    ]);

    // Edit the entity but do not enter any scheduling dates, and check that
    // only messages 5 and 6 are shown.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $assert->pageTextContains($message5);
    $assert->pageTextContains($message6);
    $assert->pageTextNotContains($message7);
    $assert->pageTextNotContains($message8);

    // Edit the entity, set a publish_on date, and check that message 5 is now
    // not shown and we get message 7 instead.
    $edit = [
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'publish_on[0][value][time]' => date('H:i:s', strtotime('+1 day', $this->requestTime)),
    ];
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $assert->pageTextNotContains($message5);
    $assert->pageTextContains($message6);
    $assert->pageTextContains($message7);
    $assert->pageTextNotContains($message8);

    // Edit the entity again, set an unpublish_on date, and check that message 6
    // is now not shown and we get message 8 instead.
    $edit = [
      'unpublish_on[0][value][date]' => date('Y-m-d', strtotime('+2 day', $this->requestTime)),
      'unpublish_on[0][value][time]' => date('H:i:s', strtotime('+2 day', $this->requestTime)),
    ];
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');
    $assert->pageTextNotContains($message5);
    $assert->pageTextNotContains($message6);
    $assert->pageTextContains($message7);
    $assert->pageTextContains($message8);

  }

}
