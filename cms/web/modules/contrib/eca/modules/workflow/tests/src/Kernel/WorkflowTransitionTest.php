<?php

namespace Drupal\Tests\eca_workflow\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ConfigTestTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Kernel tests for the "eca_workflow" action plugin.
 *
 * @group eca
 * @group eca_workflow
 */
class WorkflowTransitionTest extends KernelTestBase {

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
    'eca_workflow',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

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
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'article');

    $this->node = Node::create([
      'title' => 'Test node',
      'type' => 'article',
    ]);
    $this->node->save();

    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->actionManager = \Drupal::service('plugin.manager.action');
  }

  /**
   * Tests with no entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testExecuteWithNoEntity(): void {
    /** @var \Drupal\eca_workflow\Plugin\Action\WorkflowTransition $workflowTransition */
    $workflowTransition = $this->actionManager->createInstance('eca_workflow_transition:editorial', []);
    $workflowTransition->execute();
    $this->assertEquals('draft', $this->node->get('moderation_state')->value);
  }

  /**
   * Tests the transition.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testTransition(): void {
    /** @var \Drupal\eca_workflow\Plugin\Action\WorkflowTransition $workflowTransition */
    $workflowTransition = $this->actionManager
      ->createInstance('eca_workflow_transition:editorial', [
        'new_state' => 'published',
        'revision_log' => 'before [entity:label] after',
      ]
    );
    $this->container->get('eca.token_services')->addTokenData('entity', $this->node);

    $workflowTransition->execute($this->node);
    $this->assertEquals('draft', $this->node->get('moderation_state')->value);
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($this->node->getEntityTypeId());
    $this->assertEquals('published', $storage->loadRevision(2)
      ->get('moderation_state')->value);
    $this->assertEquals('before Test node after', $storage->loadRevision(2)
      ->get('revision_log')->value);
  }

  /**
   * Tests the access method.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessAllowed(): void {
    /** @var \Drupal\eca_workflow\Plugin\Action\WorkflowTransition $workflowTransition */
    $workflowTransition = $this->actionManager
      ->createInstance('eca_workflow_transition:editorial',
        ['new_state' => 'published']
    );

    $this->assertEquals(AccessResult::allowed(), $workflowTransition->access($this->node, NULL, TRUE));
  }

  /**
   * Tests the access method.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessTransitionNotAllowed(): void {
    /** @var \Drupal\eca_workflow\Plugin\Action\WorkflowTransition $workflowTransition */
    $workflowTransition = $this->actionManager
      ->createInstance('eca_workflow_transition:editorial',
        ['new_state' => 'archived']
    );

    $this->assertEquals(AccessResult::forbidden(), $workflowTransition->access($this->node, NULL, TRUE));
  }

}
