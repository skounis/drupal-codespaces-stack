<?php

namespace Drupal\Tests\scheduler_rules_integration\Functional;

use Drupal\Tests\scheduler\Functional\SchedulerBrowserTestBase;
use Drupal\rules\Context\ContextConfig;

/**
 * Tests the six events that Scheduler provides for use in Rules module.
 *
 * phpcs:set Drupal.Arrays.Array lineLimit 140
 *
 * @group scheduler_rules_integration
 */
class SchedulerRulesEventsTest extends SchedulerBrowserTestBase {

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
   * Rules message text strings.
   *
   * @var array
   */
  protected $message = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->rulesStorage = $this->container->get('entity_type.manager')->getStorage('rules_reaction_rule');
    $this->expressionManager = $this->container->get('plugin.manager.rules_expression');

    // Create a reaction rule to display a system message for each of the six
    // events that Scheduler triggers, for each entity type. The array of data
    // contains the event name and the text to display.
    // These rules are all active throughout all of the tests, which makes the
    // tests stronger, because it will show not only that the correct events are
    // triggered in the right places, but also that they are not triggered in
    // the wrong places.
    $rule_data = [
      // The first six events are the originals, only dispatched for Nodes.
      1 => ['scheduler_new_node_is_scheduled_for_publishing_event', 'A new node is created and is scheduled for publishing.'],
      2 => ['scheduler_existing_node_is_scheduled_for_publishing_event', 'An existing node is saved and is scheduled for publishing.'],
      3 => ['scheduler_has_published_this_node_event', 'Scheduler has published this node during cron.'],
      4 => ['scheduler_new_node_is_scheduled_for_unpublishing_event', 'A new node is created and is scheduled for unpublishing.'],
      5 => ['scheduler_existing_node_is_scheduled_for_unpublishing_event', 'An existing node is saved and is scheduled for unpublishing.'],
      6 => ['scheduler_has_unpublished_this_node_event', 'Scheduler has unpublished this node during cron.'],
      // These six events are dispatched only for Media entities.
      7 => ['scheduler:new_media_is_scheduled_for_publishing', 'A new media item is created and scheduled for publishing.'],
      8 => ['scheduler:existing_media_is_scheduled_for_publishing', 'An existing media item is saved and scheduled for publishing.'],
      9 => ['scheduler:media_has_been_published_via_cron', 'Scheduler has published this media item during cron.'],
      10 => ['scheduler:new_media_is_scheduled_for_unpublishing', 'A new media item is created and scheduled for unpublishing.'],
      11 => ['scheduler:existing_media_is_scheduled_for_unpublishing', 'An existing media item is saved and scheduled for unpublishing.'],
      12 => ['scheduler:media_has_been_unpublished_via_cron', 'Scheduler has unpublished this media item during cron.'],
      // These six events are dispatched only for Commerce Product entities.
      13 => ['scheduler:new_commerce_product_is_scheduled_for_publishing', 'A new product is created and scheduled for publishing.'],
      14 => ['scheduler:existing_commerce_product_is_scheduled_for_publishing', 'An existing product is scheduled for publishing.'],
      15 => ['scheduler:commerce_product_has_been_published_via_cron', 'Scheduler has published this product during cron.'],
      16 => ['scheduler:new_commerce_product_is_scheduled_for_unpublishing', 'A new product is created and scheduled for unpublishing.'],
      17 => ['scheduler:existing_commerce_product_is_scheduled_for_unpublishing', 'An existing product is scheduled for unpublishing.'],
      18 => ['scheduler:commerce_product_has_been_unpublished_via_cron', 'Scheduler has unpublished this product during cron.'],
      // These six events are dispatched only for Taxonomy Term entities.
      19 => ['scheduler:new_taxonomy_term_is_scheduled_for_publishing', 'A new taxonomy term is created and scheduled for publishing.'],
      20 => ['scheduler:existing_taxonomy_term_is_scheduled_for_publishing', 'An existing taxonomy term is scheduled for publishing.'],
      21 => ['scheduler:taxonomy_term_has_been_published_via_cron', 'Scheduler has published this taxonomy term during cron.'],
      22 => ['scheduler:new_taxonomy_term_is_scheduled_for_unpublishing', 'A new taxonomy term is created and scheduled for unpublishing.'],
      23 => ['scheduler:existing_taxonomy_term_is_scheduled_for_unpublishing', 'An existing taxonomy term is scheduled for unpublishing.'],
      24 => ['scheduler:taxonomy_term_has_been_unpublished_via_cron', 'Scheduler has unpublished this taxonomy term during cron.'],
    ];

    $rule = [];
    foreach ($rule_data as $i => [$event_name, $description]) {
      $rule[$i] = $this->expressionManager->createRule();
      $this->message[$i] = 'RULES message ' . $i . '. ' . $description;
      $rule[$i]->addAction('rules_system_message', ContextConfig::create()
        ->setValue('message', $this->message[$i])
        ->setValue('type', 'status')
        );
      $config_entity = $this->rulesStorage->create([
        'id' => 'rule' . $i,
        'events' => [['event_name' => $event_name]],
        'expression' => $rule[$i]->getConfiguration(),
      ]);
      $config_entity->save();
    }

    $this->drupalLogin($this->schedulerUser);
  }

  /**
   * Check the presence or absence of expected message texts on the page.
   *
   * @param string $entityTypeId
   *   The entity type being tested.
   * @param array $expectedMessages
   *   The ids of the messages that should be showing on the current page. All
   *   other messages should not be displayed.
   */
  public function checkMessages(?string $entityTypeId = NULL, array $expectedMessages = []) {
    // Add the required entity offset to each message id in the expected array.
    $offset = ['node' => 0, 'media' => 6, 'commerce_product' => 12, 'taxonomy_term' => 18];
    array_walk($expectedMessages, function (&$item) use ($offset, $entityTypeId) {
      $item = $item + $offset[$entityTypeId];
    });

    // Check that all the expected messages are shown.
    foreach ($expectedMessages as $i) {
      $this->assertSession()->pageTextContains($this->message[$i]);
    }

    // Check that none of the other messages are shown.
    $notExpecting = array_diff(array_keys($this->message), $expectedMessages);
    foreach ($notExpecting as $i) {
      $this->assertSession()->pageTextNotContains($this->message[$i]);
    }
  }

  /**
   * Tests that no events are triggered when there are no scheduling dates.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testRulesEventsNone($entityTypeId, $bundle) {
    // Add and save an entity without any scheduled dates and check that no
    // events are triggered.
    $titleField = $this->titleField($entityTypeId);
    $title = 'A. Create with no dates';
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->submitForm(["{$titleField}[0][value]" => $title], 'Save');
    $this->checkMessages();

    // Edit the entity and check that no events are triggered.
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => 'B. Edit with no dates'], 'Save');
    $this->checkMessages();
  }

  /**
   * Tests the three events related to publishing an entity.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testRulesEventsPublish($entityTypeId, $bundle) {
    // Allow dates in the past.
    $this->entityTypeObject($entityTypeId, $bundle)
      ->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // Create an entity with a publish-on date, and check that only event 1 is
    // triggered.
    $titleField = $this->titleField($entityTypeId);
    $title = 'C. Create with publish-on date';
    $edit = [
      "{$titleField}[0][value]" => $title,
      'publish_on[0][value][date]' => date('Y-m-d', time() - 60),
      'publish_on[0][value][time]' => date('H:i:s', time() - 60),
    ];
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->submitForm($edit, 'Save');
    $this->checkMessages($entityTypeId, [1]);

    // Edit the entity and check that only event 2 is triggered.
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => 'D. Edit with publish-on date'], 'Save');
    $this->checkMessages($entityTypeId, [2]);

    // Run cron and check that only event 3 is triggered.
    $this->cronRun();
    $this->drupalGet($entity->toUrl());
    $this->checkMessages($entityTypeId, [3]);
  }

  /**
   * Tests the three events related to unpublishing an entity.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testRulesEventsUnpublish($entityTypeId, $bundle) {
    // Create an entity with an unpublish-on date, and check that only event 4
    // is triggered.
    $titleField = $this->titleField($entityTypeId);
    $title = 'E. Create with unpublish-on date';
    $edit = [
      "{$titleField}[0][value]" => $title,
      'unpublish_on[0][value][date]' => date('Y-m-d', time() + 5),
      'unpublish_on[0][value][time]' => date('H:i:s', time() + 5),
    ];
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->submitForm($edit, 'Save');
    $this->checkMessages($entityTypeId, [4]);

    // Edit the entity and check that only event 5 is triggered.
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => 'F. Edit with unpublish-on date'], 'Save');
    $this->checkMessages($entityTypeId, [5]);

    // Delay to ensure that the dates are in the past so that the entity will be
    // processed during cron, and check that only event 6 is triggered.
    sleep(6);
    $this->cronRun();
    $this->drupalGet($entity->toUrl());
    $this->checkMessages($entityTypeId, [6]);
  }

  /**
   * Tests all six events related to publishing and unpublishing an entity.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testRulesEventsBoth($entityTypeId, $bundle) {
    // Allow dates in the past.
    $this->entityTypeObject($entityTypeId, $bundle)
      ->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule')->save();

    // Create an entity with both publish-on and unpublish-on dates, and check
    // that both event 1 and event 4 are triggered.
    $titleField = $this->titleField($entityTypeId);
    $title = 'G. Create with both dates';
    $edit = [
      "{$titleField}[0][value]" => $title,
      'publish_on[0][value][date]' => date('Y-m-d', time() - 60),
      'publish_on[0][value][time]' => date('H:i:s', time() - 60),
      'unpublish_on[0][value][date]' => date('Y-m-d', time() + 5),
      'unpublish_on[0][value][time]' => date('H:i:s', time() + 5),
    ];
    $this->drupalGet($this->entityAddUrl($entityTypeId, $bundle));
    $this->submitForm($edit, 'Save');
    $this->checkMessages($entityTypeId, [1, 4]);

    // Edit the entity and check that only events 2 and 5 are triggered.
    $entity = $this->getEntityByTitle($entityTypeId, $title);
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(["{$titleField}[0][value]" => 'H. Edit with both dates'], 'Save');
    $this->checkMessages($entityTypeId, [2, 5]);

    // Delay to ensure that the dates are in the past so that the entity will be
    // processed during cron, and assert that events 3, 5 and 6 are triggered.
    sleep(6);
    $this->cronRun();
    $this->drupalGet($entity->toUrl());
    $this->checkMessages($entityTypeId, [3, 5, 6]);
  }

}
