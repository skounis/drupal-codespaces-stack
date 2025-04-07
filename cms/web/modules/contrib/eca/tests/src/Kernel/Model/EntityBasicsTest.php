<?php

namespace Drupal\Tests\eca\Kernel\Model;

use Drupal\node\Entity\Node;

/**
 * Model test for entity basics.
 *
 * @group eca
 * @group eca_model
 */
class EntityBasicsTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'eca_base',
    'eca_content',
    'eca_user',
    'eca_test_model_entity_basics',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->switchUser();
  }

  /**
   * Tests entity basics on an article.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testArticle(): void {
    // Disable the 2 models that are required for ::testEntityToken.
    $this->disableEcaModel('eca_test_0002');
    $this->disableEcaModel('eca_test_0003');
    $this->assertStatusMessages([
      "Message set current user: [entity:title]",
    ]);

    $title = $this->randomMachineName();
    $titleModified = 'Article from ' . self::USER_1_NAME;
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 1,
      'title' => $title,
    ]);
    $node->save();

    $this->assertStatusMessages([
      "Made node $title sticky",
      "Promoted article $title to front page",
      "Updated title of article",
    ]);
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($node->label(), $titleModified, 'Initial article node title must change.');

    // Update the node.
    $node->save();
    $nodeId = $node->id();

    $this->assertStatusMessages([
      "Made node $titleModified sticky",
      "Promoted article $titleModified to front page",
      "Updated title of article",
      "Node $nodeId ($titleModified) was updated and ECA recognized this.",
    ]);
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($node->label(), $titleModified, 'Modified article node title must remain unchanged.');
  }

  /**
   * Tests entity basics on a basic page.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBasicPage(): void {
    // Disable the 2 models that are required for ::testEntityToken.
    $this->disableEcaModel('eca_test_0002');
    $this->disableEcaModel('eca_test_0003');
    $this->assertStatusMessages([
      "Message set current user: [entity:title]",
    ]);

    $title = $this->randomMachineName();
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'page',
      'tnid' => 0,
      'uid' => 1,
      'title' => $title,
    ]);
    $node->save();

    $this->assertStatusMessages([
      "Made node $title sticky",
    ]);
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($node->label(), $title, 'Initial page node title must remain unchanged.');

    // Update the node.
    $node->save();
    $nodeId = $node->id();

    $this->assertStatusMessages([
      "Made node $title sticky",
      "Node $nodeId ($title) was updated and ECA recognized this.",
    ]);
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($node->label(), $title, 'Initial page node title still must remain unchanged.');
  }

  /**
   * Tests entity token availability across events and models.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityToken(): void {
    // Disable model that is required for ::testArticle and ::testBasicPage.
    $this->disableEcaModel('eca_test_0004');

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
      // The event "Set current user" is not entity aware and won't replace the
      // entity token.
      "Message set current user: [entity:title]",
      // The custom event "Cplain" is not entity aware and won't replace the
      // entity token.
      "Message without event: [entity:title]",
      // The event "Pre-save" is entity aware and replaces the entity token.
      "Message 0: $title",
      // The custom event "C1" is entity aware and replaces the entity token.
      "Message 1: $title",
      // The custom event "C2" is entity aware and replaces the entity token.
      "Message 2: $title",
      // The custom event "C3" is entity aware and receives the current use
      // instead of the node and replaces the entity token.
      "Message 3: " . self::USER_1_NAME,
    ]);
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($node->label(), $title, 'Initial node title must remain unchanged.');
  }

}
