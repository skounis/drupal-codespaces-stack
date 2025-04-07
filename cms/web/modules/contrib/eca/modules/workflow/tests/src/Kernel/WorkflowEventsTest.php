<?php

namespace Drupal\Tests\eca_workflow\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ConfigTestTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\eca\Entity\Eca;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for the events provided by the ECA Workflow module.
 *
 * @group eca
 * @group eca_workflow
 */
class WorkflowEventsTest extends KernelTestBase {

  use ContentModerationTestTrait;

  use ConfigTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'content_moderation',
    'user',
    'system',
    'text',
    'workflows',
    'eca',
    'eca_test_array',
    'eca_workflow',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['system', 'content_moderation']);

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $workflow->save();
  }

  /**
   * Tests the "workflow:transition" event.
   */
  public function testTransitionEvent(): void {
    // This config does the following:
    // 1. It reacts upon several transition events
    // 2. It increments an array entry for each triggered event.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_transition_events',
      'label' => 'ECA Workflow transition events',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'initial_draft' => [
          'plugin' => 'workflow:transition',
          'label' => 'Initial draft',
          'configuration' => [
            'type' => 'node article',
            'from_state' => '',
            'to_state' => 'draft',
          ],
          'successors' => [
            ['id' => 'increment_initial_draft', 'condition' => ''],
          ],
        ],
        'draft_published' => [
          'plugin' => 'workflow:transition',
          'label' => 'Draft to published',
          'configuration' => [
            'type' => 'node article',
            'from_state' => 'draft',
            'to_state' => 'published',
          ],
          'successors' => [
            ['id' => 'increment_draft_published', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'increment_initial_draft' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment initial_draft',
          'configuration' => [
            'key' => 'initial_draft',
          ],
          'successors' => [],
        ],
        'increment_draft_published' => [
          'plugin' => 'eca_test_array_increment',
          'label' => 'Increment draft_published',
          'configuration' => [
            'key' => 'draft_published',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $node = Node::create([
      'title' => 'Test node',
      'type' => 'article',
      'status' => FALSE,
    ]);
    $node->save();

    $this->assertSame(1, ArrayIncrement::$array['initial_draft']);
    $this->assertTrue(!isset(ArrayIncrement::$array['draft_published']));

    $node->title->value = $this->randomMachineName();
    $node->save();
    $this->assertSame(1, ArrayIncrement::$array['initial_draft']);
    $this->assertTrue(!isset(ArrayIncrement::$array['draft_published']));

    $node->moderation_state->value = 'published';
    $node->save();
    $this->assertSame(1, ArrayIncrement::$array['initial_draft']);
    $this->assertSame(1, ArrayIncrement::$array['draft_published']);
  }

}
