<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\node\Entity\NodeType;

/**
 * Tests the legacy API hook functions of the Scheduler module.
 *
 * This class covers the eight original hook functions for node entity types
 * only. These are maintained for backwards-compatibility.
 *
 * @group scheduler_api
 */
class SchedulerHooksLegacyTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = [
    'scheduler_api_test',
    'scheduler_api_legacy_test',
    'menu_ui',
    'path',
  ];

  /**
   * The web user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * Node type machine_name.
   *
   * @var string
   */
  protected $customName;

  /**
   * Node type object.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $customNodetype;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Load the custom node type. It will be enabled for Scheduler automatically
    // as that is pre-configured in node.type.scheduler_api_test.yml.
    $this->customName = 'scheduler_api_node_test';
    $this->customNodetype = NodeType::load($this->customName);

    // Check that the custom node type has loaded OK.
    $this->assertNotNull($this->customNodetype, 'Custom node type "' . $this->customName . '" was created during install');

    // Create a web user that has permission to create and edit and schedule
    // the custom entity type.
    $this->webUser = $this->drupalCreateUser([
      'create ' . $this->customName . ' content',
      'edit any ' . $this->customName . ' content',
      'schedule publishing of nodes',
    ]);
    $this->webUser->set('name', 'Wenlock the Web user')->save();

  }

  /**
   * Covers hook_scheduler_nid_list($action)
   *
   * Hook_scheduler_nid_list() allows other modules to add more node ids into
   * the list to be processed. In real scenarios, the third-party module would
   * likely have more complex data structures and/or tables from which to
   * identify nodes to add. In this test, to keep it simple, we identify nodes
   * by the text of the title.
   */
  public function testNidList() {
    $this->drupalLogin($this->schedulerUser);

    // Create test nodes. Use the ordinary page type for this test, as having
    // the 'approved' fields here would unnecessarily complicate the processing.
    // Node 1 is not published and has no publishing date set. The test API
    // module will add node 1 into the list to be published.
    $node1 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => FALSE,
      'title' => 'API TEST nid_list publish me',
    ]);
    // Node 2 is published and has no unpublishing date set. The test API module
    // will add node 2 into the list to be unpublished.
    $node2 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => TRUE,
      'title' => 'API TEST nid_list unpublish me',
    ]);

    // Before cron, check node 1 is unpublished and node 2 is published.
    $this->assertFalse($node1->isPublished(), 'Before cron, node 1 "' . $node1->title->value . '" is unpublished.');
    $this->assertTrue($node2->isPublished(), 'Before cron, node 2 "' . $node2->title->value . '" is published.');

    // Run cron and refresh the nodes.
    scheduler_cron();
    $this->nodeStorage->resetCache();
    $node1 = $this->nodeStorage->load($node1->id());
    $node2 = $this->nodeStorage->load($node2->id());

    // Check node 1 is published and node 2 is unpublished.
    $this->assertTrue($node1->isPublished(), 'After cron, node 1 "' . $node1->title->value . '" is published.');
    $this->assertFalse($node2->isPublished(), 'After cron, node 2 "' . $node2->title->value . '" is unpublished.');
  }

  /**
   * Covers hook_scheduler_nid_list_alter($action)
   *
   * This hook allows other modules to add or remove node ids from the list to
   * be processed. As in testNidList() we make it simple by using the title text
   * to identify which nodes to act on.
   */
  public function testNidListAlter() {
    $this->drupalLogin($this->schedulerUser);

    // Create test nodes. Use the ordinary page type for this test, as having
    // the 'approved' fields here would unnecessarily complicate the processing.
    // Node 1 is set for scheduled publishing, but will be removed by the test
    // API hook_nid_list_alter().
    $node1 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => FALSE,
      'title' => 'API TEST nid_list_alter do not publish me',
      'publish_on' => strtotime('-1 day'),
    ]);

    // Node 2 is not published and has no publishing date set. The test API
    // module will add node 2 into the list to be published.
    $node2 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => FALSE,
      'title' => 'API TEST nid_list_alter publish me',
    ]);

    // Node 3 is set for scheduled unpublishing, but will be removed by the test
    // API hook_nid_list_alter().
    $node3 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => TRUE,
      'title' => 'API TEST nid_list_alter do not unpublish me',
      'unpublish_on' => strtotime('-1 day'),
    ]);

    // Node 4 is published and has no unpublishing date set. The test API module
    // will add node 4 into the list to be unpublished.
    $node4 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => TRUE,
      'title' => 'API TEST nid_list_alter unpublish me',
    ]);

    // Check node 1 and 2 are unpublished and node 3 and 4 are published.
    $this->assertFalse($node1->isPublished(), 'Before cron, node 1 "' . $node1->title->value . '" is unpublished.');
    $this->assertFalse($node2->isPublished(), 'Before cron, node 2 "' . $node2->title->value . '" is unpublished.');
    $this->assertTrue($node3->isPublished(), 'Before cron, node 3 "' . $node3->title->value . '" is published.');
    $this->assertTrue($node4->isPublished(), 'Before cron, node 4 "' . $node4->title->value . '" is published.');

    // Run cron and refresh the nodes.
    scheduler_cron();
    $this->nodeStorage->resetCache();
    $node1 = $this->nodeStorage->load($node1->id());
    $node2 = $this->nodeStorage->load($node2->id());
    $node3 = $this->nodeStorage->load($node3->id());
    $node4 = $this->nodeStorage->load($node4->id());

    // Check node 2 and 3 are published and node 1 and 4 are unpublished.
    $this->assertFalse($node1->isPublished(), 'After cron, node 1 "' . $node1->title->value . '" is still unpublished.');
    $this->assertTrue($node2->isPublished(), 'After cron, node 2 "' . $node2->title->value . '" is published.');
    $this->assertTrue($node3->isPublished(), 'After cron, node 3 "' . $node3->title->value . '" is still published.');
    $this->assertFalse($node4->isPublished(), 'After cron, node 4 "' . $node4->title->value . '" is unpublished.');
  }

  /**
   * Covers hook_scheduler_allow_publishing()
   *
   * This hook can allow or deny the publishing of individual nodes. This test
   * uses the customized content type which has checkboxes 'Approved for
   * publication' and 'Approved for unpublication'.
   */
  public function testAllowedPublishing() {
    $this->drupalLogin($this->webUser);

    // Check the 'approved for publishing' field is shown on the node form.
    $this->drupalGet('node/add/' . $this->customName);
    $this->assertSession()->fieldExists('edit-field-approved-publishing-value');

    // Check that the message is shown when scheduling a node for publishing
    // which is not yet allowed to be published.
    $edit = [
      'title[0][value]' => 'Set publish-on date without approval',
      'publish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'publish_on[0][value][time]' => date('H:i:s', time() + 3),
    ];
    $this->drupalGet("node/add/{$this->customName}");
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextMatches('/is scheduled for publishing.* but will not be published until approved/');

    // Create a node that is scheduled but not approved for publication. Then
    // simulate a cron run, and check that the node is still not published.
    $node = $this->createUnapprovedNode('publish_on');
    scheduler_cron();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertFalse($node->isPublished(), "Unapproved '{$node->label()}' should not be published during cron processing.");

    // Create a node and approve it for publication, simulate a cron run and
    // check that the node is published. This is a stronger test than simply
    // approving the previously used node above, as we do not know what publish
    // state that may be in after the cron run above.
    $node = $this->createUnapprovedNode('publish_on');
    $this->approveNode($node->id(), 'field_approved_publishing');
    $this->assertFalse($node->isPublished(), "New approved '{$node->label()}' should not be initially published.");
    scheduler_cron();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertTrue($node->isPublished(), "Approved '{$node->label()}' should be published during cron processing.");

    // Turn on immediate publication of nodes with publication dates in the past
    // and repeat the tests. It is not needed to simulate cron runs here.
    $this->customNodetype->setThirdPartySetting('scheduler', 'publish_past_date', 'publish')->save();
    $node = $this->createUnapprovedNode('publish_on');
    $this->assertFalse($node->isPublished(), "New unapproved '{$node->label()}' with a date in the past should not be published immediately after saving.");

    // Check that the node can be approved and published programmatically.
    $this->approveNode($node->id(), 'field_approved_publishing');
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertTrue($node->isPublished(), "New approved '{$node->label()}' with a date in the past should be published immediately when created programmatically.");

    // Check that a node can be approved and published via edit form.
    $node = $this->createUnapprovedNode('publish_on');
    $this->drupalGet("node/{$node->id()}/edit");
    $this->submitForm(['field_approved_publishing[value]' => '1'], 'Save');
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertTrue($node->isPublished(), "Approved '{$node->label()}' with a date in the past should be published immediately after saving via edit form.");

    // Show the dblog messages.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/dblog');
  }

  /**
   * Covers hook_scheduler_allow_unpublishing()
   *
   * This hook can allow or deny the unpublishing of individual nodes. This test
   * is simpler than the test sequence for allowed publishing, because the past
   * date 'publish' option is not applicable.
   */
  public function testAllowedUnpublishing() {
    $this->drupalLogin($this->webUser);

    // Check the 'approved for unpublishing' field is shown on the node form.
    $this->drupalGet('node/add/' . $this->customName);
    $this->assertSession()->fieldExists('edit-field-approved-unpublishing-value');

    // Check that the message is shown when scheduling a node for unpublishing
    // which is not yet allowed to be unpublished.
    $edit = [
      'title[0][value]' => 'Set unpublish-on date without approval',
      'unpublish_on[0][value][date]' => date('Y-m-d', time() + 3),
      'unpublish_on[0][value][time]' => date('H:i:s', time() + 3),
    ];
    $this->drupalGet("node/add/{$this->customName}");
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextMatches('/is scheduled for unpublishing.* but will not be unpublished until approved/');

    // Create a node that is scheduled but not approved for unpublication. Then
    // simulate a cron run, and check that the node is still published.
    $node = $this->createUnapprovedNode('unpublish_on');
    scheduler_cron();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertTrue($node->isPublished(), "Unapproved '{$node->label()}' should not be unpublished during cron processing.");

    // Create a node, then approve it for unpublishing, simulate a cron run and
    // check that the node is now unpublished.
    $node = $this->createUnapprovedNode('unpublish_on');
    $this->approveNode($node->id(), 'field_approved_unpublishing');
    $this->assertTrue($node->isPublished(), "New approved '{$node->label()}' should initially remain published.");
    scheduler_cron();
    $this->nodeStorage->resetCache([$node->id()]);
    $node = $this->nodeStorage->load($node->id());
    $this->assertFalse($node->isPublished(), "Approved '{$node->label()}' should be unpublished during cron processing.");

    // Show the dblog messages.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/dblog');
  }

  /**
   * Creates a new node that is not approved.
   *
   * The node has a publish/unpublish date in the past to make sure it will be
   * included in the next cron run.
   *
   * @param string $date_field
   *   The Scheduler date field to set, either 'publish_on' or 'unpublish_on'.
   *
   * @return \Drupal\node\NodeInterface
   *   A node object.
   */
  protected function createUnapprovedNode($date_field) {
    $settings = [
      'title' => (($date_field == 'publish_on') ? 'Blue' : 'Red') . " legacy node {$this->randomMachineName(10)}",
      'status' => ($date_field == 'unpublish_on'),
      $date_field => strtotime('-1 day'),
      'field_approved_publishing' => 0,
      'field_approved_unpublishing' => 0,
      'type' => $this->customName,
    ];
    return $this->drupalCreateNode($settings);
  }

  /**
   * Approves a node for publication or unpublication.
   *
   * @param int $nid
   *   The id of the node to approve.
   * @param string $field_name
   *   The name of the field to set, either 'field_approved_publishing' or
   *   'field_approved_unpublishing'.
   */
  protected function approveNode($nid, $field_name) {
    $this->nodeStorage->resetCache([$nid]);
    $node = $this->nodeStorage->load($nid);
    $node->set($field_name, TRUE);
    $node->set('title', $node->label() . " - approved for publishing: {$node->field_approved_publishing->value}, for unpublishing: {$node->field_approved_unpublishing->value}")->save();
  }

  /**
   * Test the hooks which allow hiding of scheduler input fields.
   *
   * This covers hook_scheduler_hide_publish_on_field and
   * hook_scheduler_hide_unpublish_on_field.
   */
  public function testHideField() {
    $this->drupalLogin($this->schedulerUser);

    // Create test nodes.
    $node1 = $this->drupalCreateNode([
      'type' => $this->type,
      'title' => 'Red Legacy will not have either field hidden',
    ]);
    $node2 = $this->drupalCreateNode([
      'type' => $this->type,
      'title' => 'Orange Legacy will have the publish-on field hidden',
    ]);
    $node3 = $this->drupalCreateNode([
      'type' => $this->type,
      'title' => 'Yellow Legacy will have the unpublish-on field hidden',
    ]);
    $node4 = $this->drupalCreateNode([
      'type' => $this->type,
      'title' => 'Green Legacy will have both Scheduler fields hidden',
    ]);

    // Set the scheduler fieldset to always expand, for ease during development.
    $this->nodetype->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Node 1 'red' should have both fields displayed.
    $this->drupalGet('node/' . $node1->id() . '/edit');
    $assert->ElementExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');

    // Node 2 'orange' should have only the publish-on field hidden.
    $this->drupalGet('node/' . $node2->id() . '/edit');
    $assert->ElementNotExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');

    // Node 3 'yellow' should have only the unpublish-on field hidden.
    $this->drupalGet('node/' . $node3->id() . '/edit');
    $assert->ElementExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementNotExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');

    // Node 4 'green' should have both publish-on and unpublish-on hidden.
    $this->drupalGet('node/' . $node4->id() . '/edit');
    $assert->ElementNotExists('xpath', '//input[@id = "edit-publish-on-0-value-date"]');
    $assert->ElementNotExists('xpath', '//input[@id = "edit-unpublish-on-0-value-date"]');
  }

  /**
   * Test when other modules process the publish and unpublish actions.
   *
   * This covers hook_scheduler_publish_action and
   * hook_scheduler_unpublish_action.
   */
  public function testPublishUnpublishAction() {
    $this->drupalLogin($this->schedulerUser);

    // Create test nodes.
    $node1 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => FALSE,
      'title' => 'Red Legacy will cause a failure on publishing',
      'publish_on' => strtotime('-1 day'),
    ]);
    $node2 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => TRUE,
      'title' => 'Orange Legacy will be unpublished by the API test module not Scheduler',
      'unpublish_on' => strtotime('-1 day'),
    ]);
    $node3 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => FALSE,
      'title' => 'Yellow Legacy will be published by the API test module not Scheduler',
      'publish_on' => strtotime('-1 day'),
    ]);
    // 'green' nodes will have both fields hidden so is harder to test manually.
    // Therefore introduce a different colour.
    $node4 = $this->drupalCreateNode([
      'type' => $this->type,
      'status' => TRUE,
      'title' => 'Blue Legacy will cause a failure on unpublishing',
      'unpublish_on' => strtotime('-1 day'),
    ]);

    // Simulate a cron run.
    scheduler_cron();

    // Check the red node.
    $this->nodeStorage->resetCache([$node1->id()]);
    $node1 = $this->nodeStorage->load($node1->id());
    $this->assertFalse($node1->isPublished(), 'The red node is still unpublished.');
    $this->assertNotEmpty($node1->publish_on->value, 'The red node still has a publish-on date.');

    // Check the orange node.
    $this->nodeStorage->resetCache([$node2->id()]);
    $node2 = $this->nodeStorage->load($node2->id());
    $this->assertFalse($node2->isPublished(), 'The orange node was unpublished by the API test module.');
    $this->assertNotEmpty(stristr($node2->title->value, 'unpublishing processed by API test module'), 'The orange node was processed by the API test module.');
    $this->assertEmpty($node2->unpublish_on->value, 'The orange node no longer has an unpublish-on date.');

    // Check the yellow node.
    $this->nodeStorage->resetCache([$node3->id()]);
    $node3 = $this->nodeStorage->load($node3->id());
    $this->assertTrue($node3->isPublished(), 'The yellow node was published by the API test module.');
    $this->assertNotEmpty(stristr($node3->title->value, 'publishing processed by API test module'), 'The yellow node was processed by the API test module.');
    $this->assertEmpty($node3->publish_on->value, 'The yellow node no longer has a publish-on date.');

    // Check the blue node.
    $this->nodeStorage->resetCache([$node4->id()]);
    $node4 = $this->nodeStorage->load($node4->id());
    $this->assertTrue($node4->isPublished(), 'The green node is still published.');
    $this->assertNotEmpty($node4->unpublish_on->value, 'The green node still has an unpublish-on date.');

  }

}
