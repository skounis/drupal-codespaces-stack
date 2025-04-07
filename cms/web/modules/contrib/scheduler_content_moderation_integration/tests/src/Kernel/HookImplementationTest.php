<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests the Scheduler hook functions implemented by this module.
 *
 * Increase the allowed array line length. This can be removed when the Thunder
 * drupal-testing workflow on Github reads a module's own phpcs.xml.dist file.
 * See https://github.com/thunder/drupal-testing/issues/67
 * phpcs:set Drupal.Arrays.Array lineLimit 110
 *
 * @group scheduler_content_moderation_integration
 */
class HookImplementationTest extends SchedulerContentModerationTestBase {

  /**
   * A node of a type which is enabled for moderation.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $moderatedNode;

  /**
   * A node of a type which is not enabled for moderation.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $nonModeratedNode;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a user which has any permission required.
    $user = $this->createMock('Drupal\Core\Session\AccountInterface');
    $user->method('hasPermission')->willReturn(TRUE);
    $this->container->set('current_user', $user);

    // Create a test 'Example' node which will be moderated.
    $this->moderatedNode = Node::create([
      'type' => 'example',
      'title' => 'Example content is moderated',
      'moderation_state' => 'starting_state',
    ]);

    // Create a test 'Other' node which will not be moderated.
    $this->nonModeratedNode = Node::create([
      'type' => 'other',
      'title' => 'Other is not moderated',
    ]);
  }

  /**
   * Tests if the Scheduler Publish-on and Unpublish-on fields should be hidden.
   *
   * @dataProvider hideSchedulerFieldsProvider
   */
  public function testHookHideSchedulerFields($expected, $nodeChoice, $options): void {
    $node = $this->$nodeChoice;

    // Create the starting state, so that we can add the specific new states and
    // transitions required for each test case.
    $this->workflow->getTypePlugin()->addState('starting_state', 'Starting state');
    $this->workflow->save();
    $config_additions['states']['starting_state'] = ['published' => TRUE, 'default_revision' => TRUE];

    foreach ($options as $key => $value) {
      // Define a published state and a transition from starting_state to it.
      $this->workflow->getTypePlugin()->addState("published_$key", "Published State $key $value", TRUE, TRUE);
      $config_additions['states']["published_$key"] = ['published' => TRUE, 'default_revision' => TRUE];
      $this->workflow->getTypePlugin()
        ->addTransition("publish_transition_$key", "Transition into published $key $value", ['starting_state'], "published_$key");

      // Define an unpublished state and a transition from starting_state to it.
      $this->workflow->getTypePlugin()->addState("unpublished_$key", "Unpublished State $key $value", TRUE, TRUE);
      $config_additions['states']["unpublished_$key"] = ['published' => FALSE, 'default_revision' => TRUE];
      $this->workflow->getTypePlugin()
        ->addTransition("unpublish_transition_$key", "Transition into unpublished $key $value", ['starting_state'], "unpublished_$key");
    }
    $config = $this->workflow->getTypePlugin()->getConfiguration();
    $this->workflow->getTypePlugin()->setConfiguration(array_merge_recursive($config, $config_additions));
    $this->workflow->save();

    $result = scheduler_content_moderation_integration_scheduler_hide_publish_date([], [], $node);
    $this->assertEquals($expected, $result, sprintf('Hide the publish-on field: Expected %s, Result %s', $expected ? 'Yes' : 'No', $result ? 'Yes' : 'No'));

    $result = scheduler_content_moderation_integration_scheduler_hide_unpublish_date([], [], $node);
    $this->assertEquals($expected, $result, sprintf('Hide the unpublish-on field: Expected %s, Result %s', $expected ? 'Yes' : 'No', $result ? 'Yes' : 'No'));
  }

  /**
   * Data provider for self:testHookHideSchedulerFields().
   */
  public static function hideSchedulerFieldsProvider(): array {
    return [
      // Two states in addition to _none. Should not hide the fields.
      [FALSE, 'moderatedNode', ['Some state', 'Not hidden']],

      // Just one state in addition to _none. Should not hide the fields.
      [FALSE, 'moderatedNode', ['The only state']],

      // No states at all. This should cause the fields to be hidden.
      [TRUE, 'moderatedNode', []],

      // Content type is not moderated. Should not hide the fields.
      [FALSE, 'nonModeratedNode', []],
    ];
  }

}
