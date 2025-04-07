<?php

namespace Drupal\Tests\eca\Kernel\Model;

use Drupal\node\Entity\Node;

/**
 * Model test for token forwarding to custom events.
 *
 * @group eca
 * @group eca_model
 */
class TokenForwardTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'eca_base',
    'eca_content',
    'eca_user',
    'eca_test_model_token_forward',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->switchUser(1);
  }

  /**
   * Tests token forwarding to custom events.
   */
  public function testTokenForward(): void {
    // Create a node.
    $title = $this->randomMachineName();

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 1,
      'title' => $title,
    ]);
    $node->save();

    $this->assertStatusMessages([
      "From CE1: we received user '[some_user:account-name]' and node '[entity:title]'",
      "From CE2: we received user '" . self::USER_1_NAME . "' and node '$title'",
    ]);
    $this->assertNoMessages();
    $this->assertNoError();
  }

}
