<?php

namespace Drupal\Tests\eca\Kernel\Model;

use Drupal\node\Entity\Node;

/**
 * Model test for the action set_field_value.
 *
 * @group eca
 * @group eca_model
 */
class SetFieldValueTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'eca_base',
    'eca_content',
    'eca_test_model_set_field_value',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->switchUser(1);
  }

  /**
   * Tests setting field values.
   */
  public function testSetFieldValues(): void {
    // Create a node.
    $title = $this->randomMachineName();
    $text_line = "Title is $title.";
    $text_line_updated = 'The updated text line content.';
    $text_lines_1 = 'Line 1';
    $text_lines_2 = 'Second line';
    $text_lines_3 = 'Line 3';
    $text_lines_4 = 'Line 4';
    $text_lines_inserted = 'Inserted line';
    $text_lines_reset = 'This is one line.';

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'type_set_field_value',
      'tnid' => 0,
      'uid' => 1,
      'title' => $title,
    ]);

    // Test block 1: save the new node and assert title,
    // single and multi value text line fields.
    $node->save();

    $lines = $node->get('field_text_lines')->getValue();
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($title, $node->label(), 'New node, title has wrong value.');
    $this->assertEquals($text_line, $node->get('field_text_line')->value, 'New node, text line does not match.');
    $this->assertEquals($text_lines_1, $lines[0]['value'], 'New node, text lines 1 does not match.');
    $this->assertNull($lines[1] ?? NULL, 'New node, text lines 2 should be undefined.');
    $this->assertNull($lines[2] ?? NULL, 'New node, text lines 3 should be undefined.');

    // Test block 2: save the node again, nothing should have changed
    // except the new title.
    $title = $this->randomMachineName();
    $node->setTitle($title);
    $node->save();

    $lines = $node->get('field_text_lines')->getValue();
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($title, $node->label(), 'Updated node, title has wrong value.');
    $this->assertEquals($text_line, $node->get('field_text_line')->value, 'Updated node, text line does not match.');
    $this->assertEquals($text_lines_1, $lines[0]['value'], 'Updated node, text lines 1 does not match.');
    $this->assertNull($lines[1] ?? NULL, 'Updated node, text lines 2 should be undefined.');
    $this->assertNull($lines[2] ?? NULL, 'Updated node, text lines 3 should be undefined.');

    // Test block 3: save the node again with "append" in the title. The single
    // text line should be updated and the multi value text line filled, the
    // fourth value being ignored.
    $title = $this->randomMachineName() . 'append';
    $node->setTitle($title);
    $node->save();

    $lines = $node->get('field_text_lines')->getValue();
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($title, $node->label(), 'Updated node to append, title has wrong value.');
    $this->assertEquals($text_line_updated, $node->get('field_text_line')->value, 'Updated node to append, text line does not match.');
    $this->assertEquals($text_lines_1, $lines[0]['value'], 'Updated node to append, text lines 1 does not match.');
    $this->assertEquals($text_lines_2, $lines[1]['value'], 'Updated node to append, text lines 2 does not match.');
    $this->assertEquals($text_lines_3, $lines[2]['value'], 'Updated node to append, text lines 3 does not match.');
    $this->assertNull($lines[3] ?? NULL, 'Updated node to append, text lines 4 should be undefined.');

    // Test block 4: save the node again with "drop first" in the title. The
    // multi value text line should have the first item dropped and the fourth
    // being appended.
    $title = $this->randomMachineName() . 'drop first';
    $node->setTitle($title);
    $node->save();

    $lines = $node->get('field_text_lines')->getValue();
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($title, $node->label(), 'Updated node to drop first, title has wrong value.');
    $this->assertEquals($text_line_updated, $node->get('field_text_line')->value, 'Updated node to drop first, text line does not match.');
    $this->assertEquals($text_lines_2, $lines[0]['value'], 'Updated node to drop first, text lines 1 does not match.');
    $this->assertEquals($text_lines_3, $lines[1]['value'], 'Updated node to drop first, text lines 2 does not match.');
    $this->assertEquals($text_lines_4, $lines[2]['value'], 'Updated node to drop first, text lines 3 does not match.');
    $this->assertNull($lines[3] ?? NULL, 'Updated node to drop first, text lines 4 should be undefined.');

    // Test block 5: save the node again with "drop last" in the title. The
    // multi value text line should have line 1 inserted and the last one
    // being dropped.
    $title = $this->randomMachineName() . 'drop last';
    $node->setTitle($title);
    $node->save();

    $lines = $node->get('field_text_lines')->getValue();
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($title, $node->label(), 'Updated node to drop last, title has wrong value.');
    $this->assertEquals($text_lines_inserted, $lines[0]['value'], 'Updated node to drop last, text lines 1 does not match.');
    $this->assertEquals($text_lines_2, $lines[1]['value'], 'Updated node to drop last, text lines 2 does not match.');
    $this->assertEquals($text_lines_3, $lines[2]['value'], 'Updated node to drop last, text lines 3 does not match.');
    $this->assertNull($lines[3] ?? NULL, 'Updated node to drop last, text lines 4 should be undefined.');

    // Test block 6: save the node again with "reset" in the title. The
    // multi value text line should have be cleared and the first line set to
    // a single value.
    $title = $this->randomMachineName() . 'reset';
    $node->setTitle($title);
    $node->save();

    $lines = $node->get('field_text_lines')->getValue();
    $this->assertNoMessages();
    $this->assertNoError();
    $this->assertEquals($title, $node->label(), 'Updated node to reset, title has wrong value.');
    $this->assertEquals($text_lines_reset, $lines[0]['value'], 'Updated node to reset, text lines 1 does not match.');
    $this->assertNull($lines[1] ?? NULL, 'Updated node to reset, text lines 2 should be undefined.');
    $this->assertNull($lines[2] ?? NULL, 'Updated node to reset, text lines 3 should be undefined.');
  }

}
