<?php

namespace Drupal\Tests\eca\Kernel\Model;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\node\Entity\Node;

/**
 * Model test for logging.
 *
 * @group eca
 * @group eca_model
 */
class LoggingTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'eca_content',
    'eca_log',
    'eca_test_model_logging',
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
   * Tests logging on creating/saving an article.
   */
  public function testArticle(): void {
    $title = $this->randomMachineName();
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 1,
      'title' => $title,
    ]);
    $node->save();

    $this->assertNoMessages();
    $this->assertNoError([
      new LogRecord(RfcLogLevel::INFO, 'eca', 'Node @label is about to be saved', ['@label' => $title]),
    ]);
  }

}
