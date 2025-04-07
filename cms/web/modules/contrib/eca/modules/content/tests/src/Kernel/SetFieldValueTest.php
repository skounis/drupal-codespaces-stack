<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_set_field_value" action plugin.
 *
 * @group eca
 * @group eca_content
 */
class SetFieldValueTest extends KernelTestBase {

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_content',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // Set state so that \Drupal\eca\Processor::isEcaContext returns TRUE for
    // \Drupal\eca_content\Plugin\Action\FieldUpdateActionBase::save, even if
    // ECA actions plugin "eca_set_field_value" gets executed without an event.
    \Drupal::state()->set('_eca_internal_test_context', TRUE);

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);
  }

  /**
   * Tests setting field values on a node body field.
   */
  public function testNodeBody() {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $body = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);
    $node = $this->getNodeWithBody('123', $body, $summary);

    // Create an action that sets the body value of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('body', '123');
    $this->assertFalse($action->access($node), 'User without permissions must not have access to change the field.');

    // Same as above, but using the "value" column explicitly.
    $action = $this->getActionSetClear('body.value', '456');
    $this->assertFalse($action->access($node), 'User without permissions must not have access to change the field.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));

    // Create an action that sets the body value of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('body', '123');
    $this->assertTrue($action->access($node), 'User with permissions must have access to change the field.');
    $this->assertEquals($body, $node->body->value, 'Original body value before action execution must remain the same.');
    $action->execute($node);
    $this->assertEquals('123', $node->body->value, 'After action execution, the body value must have been changed.');

    // Same as above, but using the "value" column explicitly.
    $action = $this->getActionSetClear('body.value', '456');
    $this->assertTrue($action->access($node), 'User with permissions must have access to change the field.');
    $action->execute($node);
    $this->assertEquals('456', $node->body->value, 'After action execution, the body value must have been changed.');

    // Using set:empty method which should not change the value,
    // because it was set before.
    $action = $this->getAction('set:empty', 'body.value', '555');
    $action->execute($node);
    $this->assertEquals('456', $node->body->value, 'After action execution, the body value must not have been changed because it was set before.');

    $token_services->addTokenData('node', $node);
    $action = $this->getAction('remove', 'body.value', '[node:body:value]');
    $action->execute($node);
    $this->assertEquals('', $node->body->value, 'Body value got removed and therefore must be empty.');

    // Using set:empty method now which should change the value,
    // because the body value is currently empty.
    $action = $this->getAction('set:empty', 'body.value', '555');
    $action->execute($node);
    $this->assertEquals('555', $node->body->value, 'The body value must have been changed because it was empty.');

    // Now setting the summary value.
    $action = $this->getActionSetClear('body.summary', '8888');
    $action->execute($node);
    $this->assertEquals('555', $node->body->value, 'The body value must not have been changed.');
    $this->assertEquals('8888', $node->body->summary, 'The body summary must have been changed.');
    $action = $this->getAction('set:empty', 'body.summary', '9');
    $action->execute($node);
    $this->assertEquals('555', $node->body->value, 'The body value must not have been changed.');
    $this->assertEquals('8888', $node->body->summary, 'The body summary must not have been changed.');

    // Use an explicit delta.
    $action = $this->getActionSetClear('body.0.value', '1000');
    $action->execute($node);
    $this->assertEquals('1000', $node->body->value, 'The body value must have been changed.');
    $this->assertEquals('8888', $node->body->summary, 'The body summary must not have been changed.');
    $action = $this->getActionSetClear('body.0.summary', '111111');
    $action->execute($node);
    $this->assertEquals('1000', $node->body->value, 'The body value must not have been changed.');
    $this->assertEquals('111111', $node->body->summary, 'The body summary must not have been changed.');

    $action = $this->getActionSetClear('body.0', '33333');
    $action->execute($node);
    $this->assertEquals('33333', $node->body->value, 'The body value must have been changed.');
    $this->assertEquals('111111', $node->body->summary, 'The body summary must not have been changed.');

    // Trying to set an invalid delta must throw an exception.
    $action = $this->getActionSetClear('body.2.value', '7777777');
    $exception = NULL;
    try {
      $action->execute($node);
    }
    catch (\Exception $thrown) {
      $exception = $thrown;
    }
    finally {
      $this->assertTrue($exception instanceof \InvalidArgumentException, 'Trying to set an invalid delta must throw an exception.');
    }
    $this->assertEquals('33333', $node->body->value, 'The body value must not have been changed.');
    $this->assertEquals('111111', $node->body->summary, 'The body summary must not have been changed.');

    $body = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);
    $another_node = $this->getNodeWithBody('456', $body, $summary);

    $token_services->addTokenData('another', $another_node);

    $action = $this->getActionSetClear('body', '[another:body]');
    $action->execute($node);
    $this->assertEquals($body, $node->body->value, 'The body value must have been changed to the value of another node.');
    $this->assertEquals($summary, $node->body->summary, 'The body summary must have been changed to the summary of another node.');

    $another_node->body->value = '222111';
    $node->body->summary = '000000';
    $action = $this->getActionSetClear('body:value', '[another:body]');
    $action->execute($node);
    $this->assertEquals('222111', $node->body->value, 'The body value must have been changed to the value of another node.');
    $this->assertEquals('000000', $node->body->summary, 'The body summary must remain unchanged.');

    $body = $this->randomMachineName(32);
    $another_node->body->value = $body;
    $summary = $this->randomMachineName(16);
    $another_node->body->summary = $summary;
    $action = $this->getActionSetClear('[body:summary]', '[another:body:summary]');
    $action->execute($node);
    $this->assertEquals('222111', $node->body->value, 'The body value must remain unchanged.');
    $this->assertEquals($summary, $node->body->summary, 'The body summary must have been changed to the value of another node.');
    $this->assertEquals($body, $another_node->body->value, 'The body value of another node must remain unchanged.');

    // Removing a value by using the clear method.
    $action = $this->getActionSetClear('body:value', '');
    $action->execute($node);
    $this->assertEquals('', $node->body->value, 'The body value must be empty.');
    $this->assertEquals($summary, $node->body->summary, 'The body summary must not have been changed.');
    $node->body->value = $this->randomMachineName(32);
    $action = $this->getActionSetClear('body', '');
    $action->execute($node);
    $this->assertNull($node->body->value, 'The body value must be unset.');
    $this->assertNull($node->body->summary, 'The summary must be unset.');

    $account_switcher->switchBack();
  }

  /**
   * Tests setting a multi-value string and multi-value text-with-summary field.
   */
  public function testNodeStringMultiple() {
    // Create the multi-value string field, using cardinality 3.
    $field_definition = FieldStorageConfig::create([
      'field_name' => 'field_string_multi',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => 3,
    ]);
    $field_definition->save();
    $instance = FieldConfig::create([
      'field_name' => 'field_string_multi',
      'label' => 'A string field having multiple values.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $instance->save();
    // Create the multi-value text-with-summary field, unlimited cardinality.
    $field_definition = FieldStorageConfig::create([
      'field_name' => 'field_text_multi',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_definition->save();
    $instance = FieldConfig::create([
      'field_name' => 'field_text_multi',
      'label' => 'A text with summary field having multiple values.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $instance->save();

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $string = $this->randomMachineName(32);
    $text = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);
    $node = $this->getNodeWithTextMulti($string, $text, $summary);

    // Create an action that sets a string value of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('field_string_multi', '123');
    $this->assertFalse($action->access($node), 'User without permissions must not have access to change the field.');
    // Same as above, but using the "value" column explicitly.
    $action = $this->getActionSetClear('field_string_multi.value', '456');
    $this->assertFalse($action->access($node), 'User without permissions must not have access to change the field.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));
    // Create an action that sets the body value of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('field_string_multi', '123');
    $this->assertTrue($action->access($node), 'User with permissions must have access to change the field.');
    $this->assertEquals($string, $node->field_string_multi[0]->value, 'Original field_string_multi[0] value before action execution must remain the same.');
    $this->assertEquals($string . '2', $node->field_string_multi[1]->value, 'Original field_string_multi[1] value before action execution must remain the same.');
    $this->assertEquals($string . '3', $node->field_string_multi[2]->value, 'Original field_string_multi[2] value before action execution must remain the same.');
    $action->execute($node);
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(!isset($node->field_string_multi[1]), 'Second value must not be set anymore.');
    $this->assertTrue(!isset($node->field_string_multi[2]), 'Third value must not be set anymore.');
    $this->assertEquals('123', $node->field_string_multi[0]->value, 'After action execution, the field_string_multi value must have been changed.');
    $this->assertCount(1, $node->get('field_string_multi'));

    // Same as above, but using the "value" column explicitly.
    $action = $this->getActionSetClear('field_string_multi.value', '456');
    $this->assertTrue($action->access($node), 'User with permissions must have access to change the field.');
    $action->execute($node);
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(!isset($node->field_string_multi[1]), 'Second value must not be set.');
    $this->assertTrue(!isset($node->field_string_multi[2]), 'Third value must not be set.');
    $this->assertEquals('456', $node->field_string_multi[0]->value, 'After action execution, the field_string_multi value must have been changed.');

    // Append a value.
    $action = $this->getAction('append:drop_first', 'field_string_multi', '11111');
    $action->execute($node);
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_string_multi[1]), 'Second value must be set.');
    $this->assertTrue(!isset($node->field_string_multi[2]), 'Third value must not be set.');
    $this->assertEquals('456', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('11111', $node->field_string_multi[1]->value, 'Second value must now be set with appended value.');
    // Append another one.
    $action = $this->getAction('append:drop_last', 'field_string_multi:value', '222222222');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('456', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('11111', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[2]->value, 'Third value must now be set with appended value.');

    // Prepend a value.
    $action = $this->getAction('prepend:drop_first', '[field_string_multi:value]', '33333');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('33333', $node->field_string_multi[0]->value, 'First value must have been changed.');
    $this->assertEquals('11111', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');

    // Set a value using an explicit delta.
    $action = $this->getActionSetClear('field_string_multi.1.value', '444444444');
    $action->execute($node);
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_string_multi[1]), 'Second value must be set.');
    $this->assertTrue(isset($node->field_string_multi[2]), 'Third value must be set.');
    $this->assertEquals('33333', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('444444444', $node->field_string_multi[1]->value, 'Second value must have been changed.');
    $this->assertEquals('222222222', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');

    $action = $this->getAction('append:not_full', 'field_string_multi', '121212121212');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('33333', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('444444444', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');
    $action = $this->getAction('append:drop_first', 'field_string_multi', '121212121212');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('444444444', $node->field_string_multi[0]->value, 'First value must have gotten value from second entry.');
    $this->assertEquals('222222222', $node->field_string_multi[1]->value, 'Second value must have gotten value from third entry.');
    $this->assertEquals('121212121212', $node->field_string_multi[2]->value, 'Third value must have been changed.');
    // This action would do nothing, because the value already exists.
    $action = $this->getAction('append:drop_last', 'field_string_multi', '121212121212');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('444444444', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('121212121212', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');
    $action = $this->getAction('append:drop_last', 'field_string_multi', '9898988');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('444444444', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('9898988', $node->field_string_multi[2]->value, 'Third value must have been changed.');
    $action = $this->getAction('prepend:not_full', 'field_string_multi', '55555555555');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('444444444', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('9898988', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');
    $action = $this->getAction('prepend:drop_first', 'field_string_multi', '55555555555');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('55555555555', $node->field_string_multi[0]->value, 'First value must have been changed.');
    $this->assertEquals('222222222', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('9898988', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');
    // This action would do nothing, because the value already exists.
    $action = $this->getAction('prepend:drop_last', 'field_string_multi', '55555555555');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('55555555555', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('9898988', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');
    $action = $this->getAction('prepend:drop_last', 'field_string_multi', 'v8');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('v8', $node->field_string_multi[0]->value, 'First value must have been changed.');
    $this->assertEquals('55555555555', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[2]->value, 'Third value must have been changed.');
    $action = $this->getAction('remove', 'field_string_multi', 'tttttttt');
    $action->execute($node);
    $this->firstThreeStringMultiSet($node);
    $this->assertEquals('v8', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('55555555555', $node->field_string_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('222222222', $node->field_string_multi[2]->value, 'Third value must remain unchanged.');
    $action = $this->getAction('remove', 'field_string_multi', '222222222');
    $action->execute($node);
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_string_multi[1]), 'Second value must be set.');
    $this->assertTrue(!isset($node->field_string_multi[2]), 'Third value must not be set.');
    $this->assertTrue(!isset($node->field_string_multi[3]), 'No fourth value must be set.');
    $this->assertEquals('v8', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('55555555555', $node->field_string_multi[1]->value, 'Second value must have gotten value from third entry.');
    $action = $this->getAction('remove', 'field_string_multi', '55555555555');
    $action->execute($node);
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(!isset($node->field_string_multi[1]), 'Second value must not be set.');
    $this->assertTrue(!isset($node->field_string_multi[2]), 'Third value must not be set.');
    $this->assertTrue(!isset($node->field_string_multi[3]), 'No fourth value must be set.');
    $this->assertEquals('v8', $node->field_string_multi[0]->value, 'First value must remain unchanged.');
    $action = $this->getAction('remove', 'field_string_multi', 'v8');
    $action->execute($node);
    $this->assertCount(0, $node->get('field_string_multi'), 'The field must be empty.');

    $account_switcher->switchBack();

    // Create an action that sets a string value of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('field_text_multi', '123');
    $this->assertFalse($action->access($node), 'User without permissions must not have access to change the field.');
    // Same as above, but using the "value" column explicitly.
    $action = $this->getActionSetClear('field_text_multi.value', '456');
    $this->assertFalse($action->access($node), 'User without permissions must not have access to change the field.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));
    // Create an action that sets the text value of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('field_text_multi', '123');
    $this->assertTrue($action->access($node), 'User with permissions must have access to change the field.');
    $this->assertEquals($text, $node->field_text_multi[0]->value, 'Original field_text_multi[0] value before action execution must remain the same.');
    $this->assertEquals($text . '2', $node->field_text_multi[1]->value, 'Original field_text_multi[1] value before action execution must remain the same.');
    $this->assertEquals($text . '3', $node->field_text_multi[2]->value, 'Original field_text_multi[2] value before action execution must remain the same.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'Original field_text_multi[0] summary before action execution must remain the same.');
    $this->assertEquals($summary . '2', $node->field_text_multi[1]->summary, 'Original field_text_multi[1] summary before action execution must remain the same.');
    $this->assertEquals($summary . '3', $node->field_text_multi[2]->summary, 'Original field_text_multi[2] summary before action execution must remain the same.');
    $action->execute($node);
    $this->assertTrue(isset($node->field_text_multi[0]), 'First value must be set.');
    $this->assertTrue(!isset($node->field_text_multi[1]), 'Second value must not be set anymore.');
    $this->assertTrue(!isset($node->field_text_multi[2]), 'Third value must not be set anymore.');
    $this->assertEquals('123', $node->field_text_multi[0]->value, 'After action execution, the field_text_multi value must have been changed.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'Original field_text_multi[0] summary must remain the same.');
    $this->assertCount(1, $node->get('field_text_multi'));

    // Same as above, but using the "value" column explicitly.
    $action = $this->getActionSetClear('field_text_multi.value', '456');
    $this->assertTrue($action->access($node), 'User with permissions must have access to change the field.');
    $action->execute($node);
    $this->assertTrue(isset($node->field_text_multi[0]), 'First value must be set.');
    $this->assertTrue(!isset($node->field_text_multi[1]), 'Second value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[2]), 'Third value must not be set.');
    $this->assertEquals('456', $node->field_text_multi[0]->value, 'After action execution, the field_text_multi value must have been changed.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'Original field_text_multi[0] summary must remain the same.');

    // Append a value.
    $action = $this->getAction('append:drop_first', 'field_text_multi', '11111');
    $action->execute($node);
    $this->assertTrue(isset($node->field_text_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_text_multi[1]), 'Second value must be set.');
    $this->assertTrue(!isset($node->field_text_multi[2]), 'Third value must not be set.');
    $this->assertEquals('456', $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'Original field_text_multi[0] summary must remain the same.');
    $this->assertEquals('11111', $node->field_text_multi[1]->value, 'Second value must now be set with appended value.');
    $this->assertEquals('', $node->field_text_multi[1]->summary, 'Second summary must be empty.');
    // Append another one.
    $action = $this->getAction('append:drop_last', 'field_text_multi:value', '222222222');
    $action->execute($node);
    $this->firstThreeTextMultiSet($node);
    $this->assertEquals('456', $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'Original field_text_multi[0] summary must remain the same.');
    $this->assertEquals('11111', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[1]->summary, 'Second summary must be empty.');
    $this->assertEquals('222222222', $node->field_text_multi[2]->value, 'Third value must now be set with appended value.');
    $this->assertEquals('', $node->field_text_multi[2]->summary, 'Third summary must be empty.');

    // Prepend a value with explicit property.
    $action = $this->getAction('prepend:drop_first', 'field_text_multi:value', '33333');
    $action->execute($node);
    $this->firstFourValuesSet($node);
    $this->assertEquals('33333', $node->field_text_multi[0]->value, 'First value must have been changed.');
    $this->assertEquals('', $node->field_text_multi[0]->summary, 'First summary must be empty.');
    $this->assertEquals('456', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[1]->summary, 'Second summary must remain the same.');
    $this->assertEquals('11111', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[2]->summary, 'Third summary must be empty.');
    $this->assertEquals('222222222', $node->field_text_multi[3]->value, 'Fourth value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must be empty.');

    // Set a summary.
    $action = $this->getActionSetClear('field_text_multi.0.summary', '42');
    $action->execute($node);
    $this->firstFourValuesSet($node);
    $this->assertEquals('33333', $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('42', $node->field_text_multi[0]->summary, 'First summary must have been changed.');
    $this->assertEquals('456', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[1]->summary, 'Second summary must remain the same.');
    $this->assertEquals('11111', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[2]->summary, 'Third summary must be empty.');
    $this->assertEquals('222222222', $node->field_text_multi[3]->value, 'Fourth value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must be empty.');
    $action = $this->getAction('set:empty', 'field_text_multi.2.summary', '50');
    $action->execute($node);
    $this->firstFourValuesSet($node);
    $this->assertEquals('33333', $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('42', $node->field_text_multi[0]->summary, 'First summary must must remain unchanged.');
    $this->assertEquals('456', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[1]->summary, 'Second summary must remain unchanged.');
    $this->assertEquals('11111', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals('50', $node->field_text_multi[2]->summary, 'Third summary must have been changed.');
    $this->assertEquals('222222222', $node->field_text_multi[3]->value, 'Fourth value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must be empty.');
    $action = $this->getAction('set:empty', 'field_text_multi.2.summary', '51');
    $action->execute($node);
    $this->firstFourValuesSet($node);
    $this->assertEquals('33333', $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('42', $node->field_text_multi[0]->summary, 'First summary must must remain unchanged.');
    $this->assertEquals('456', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[1]->summary, 'Second summary must remain unchanged.');
    $this->assertEquals('11111', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals('50', $node->field_text_multi[2]->summary, 'Third summary must remain unchanged.');
    $this->assertEquals('222222222', $node->field_text_multi[3]->value, 'Fourth value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must be empty.');

    $action = $this->getAction('append:drop_last', 'field_text_multi.value', '50');
    $action->execute($node);
    $this->firstFourValuesSet($node);
    $this->assertTrue(isset($node->field_text_multi[4]), '5th value must be set.');
    $this->assertTrue(!isset($node->field_text_multi[5]), '6th value must not be set.');
    $this->assertEquals('33333', $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals('42', $node->field_text_multi[0]->summary, 'First summary must must remain unchanged.');
    $this->assertEquals('456', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[1]->summary, 'Second summary must remain unchanged.');
    $this->assertEquals('11111', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals('50', $node->field_text_multi[2]->summary, 'Third summary must remain unchanged.');
    $this->assertEquals('222222222', $node->field_text_multi[3]->value, 'Fourth value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must remain unchanged.');
    $this->assertEquals('222222222', $node->field_text_multi[3]->value, '5th value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, '5th summary must remain unchanged.');
    $this->assertEquals('50', $node->field_text_multi[4]->value, '6th value must have been added.');
    $this->assertEquals('', $node->field_text_multi[4]->summary, '6th summary must be empty.');

    $string = $this->randomMachineName(32);
    $text = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);
    $another_node = $this->getNodeWithTextMulti($string, $text, $summary);
    $token_services->addTokenData('another', $another_node);

    $action = $this->getActionSetClear('field_text_multi', '[another:field_text_multi]');
    $action->execute($node);
    $this->firstThreeTextMultiSet($node);
    $this->assertTrue(!isset($node->field_text_multi[4]), '5th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[5]), '6th value must not be set.');
    $this->assertEquals($text, $node->field_text_multi[0]->value, 'First value must be copied from another node.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'First summary must be copied from another node.');
    $this->assertEquals($text . '2', $node->field_text_multi[1]->value, 'Second value must be copied from another node.');
    $this->assertEquals($summary . '2', $node->field_text_multi[1]->summary, 'Second summary must be copied from another node.');
    $this->assertEquals($text . '3', $node->field_text_multi[2]->value, 'Third value must be copied from another node.');
    $this->assertEquals($summary . '3', $node->field_text_multi[2]->summary, 'Third summary must be copied from another node.');

    $action = $this->getAction('append:not_empty', 'field_text_multi', '[another:field_string_multi]');
    $action->execute($node);
    $this->firstFourValuesSet($node);
    $this->assertTrue(isset($node->field_text_multi[4]), '5th value must be set.');
    $this->assertTrue(isset($node->field_text_multi[5]), '6th value must be set.');
    $this->assertEquals($text, $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'First summary must remain unchanged.');
    $this->assertEquals($text . '2', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary . '2', $node->field_text_multi[1]->summary, 'Second summary must remain unchanged.');
    $this->assertEquals($text . '3', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals($summary . '3', $node->field_text_multi[2]->summary, 'Third summary must remain unchanged.');
    $this->assertEquals($string, $node->field_text_multi[3]->value, 'Fourth value must have copy from string field of another node.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must be empty.');
    $this->assertEquals($string . '2', $node->field_text_multi[4]->value, '5th value must have copy from string field of another node.');
    $this->assertEquals('', $node->field_text_multi[4]->summary, '5th summary must be empty.');
    $this->assertEquals($string . '3', $node->field_text_multi[5]->value, '6th value must have copy from string field of another node.');
    $this->assertEquals('', $node->field_text_multi[5]->summary, '6th summary must be empty.');

    $action = $this->getAction('append:drop_first', 'field_text_multi', '[another:field_string_multi]');
    $action->execute($node);
    $this->assertTextMultiWithSummary($node, $text, $summary, $string);

    $action = $this->getAction('set:empty', 'field_text_multi', '[another:field_string_multi]');
    $action->execute($node);
    $this->assertTextMultiWithSummary($node, $text, $summary, $string);

    $action = $this->getActionSetClear('field_text_multi', '[another:field_string_multi]');
    $action->execute($node);
    $this->firstThreeTextMultiSet($node);
    $this->assertTrue(!isset($node->field_text_multi[4]), '5th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[5]), '6th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[6]), '7th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[7]), '8th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[8]), '9th value must not be set.');
    $this->assertEquals($string, $node->field_text_multi[0]->value, 'First value must have copy from string field of another node.');
    $this->assertEquals('', $node->field_text_multi[0]->summary, 'First summary must be empty.');
    $this->assertEquals($string . '2', $node->field_text_multi[1]->value, 'Second value have copy from string field of another node.');
    $this->assertEquals('', $node->field_text_multi[1]->summary, 'Second summary must be empty.');
    $this->assertEquals($string . '3', $node->field_text_multi[2]->value, 'Third value must have copy from string field of another node.');
    $this->assertEquals('', $node->field_text_multi[2]->summary, 'Third summary must be empty.');

    $account_switcher->switchBack();
  }

  /**
   * Tests setting single references.
   */
  public function testNodeReferenceSingle() {
    $this->saveField('field_node_single', 1);
    $node1 = $this->getNode('123');
    $node2 = $this->getNode('456');

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $token_services->addTokenData('node1', $node1);
    $token_services->addTokenData('node2', $node2);

    // Create an action that sets a target of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $defaults = [
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'save_entity' => TRUE,
    ];

    $action = $this->getActionSetClear('field_node_single', $node2->id(), $defaults);
    $this->assertFalse($action->access($node1), 'User without permissions must not have access to change the field.');
    // Same as above, but using the "target_id" column explicitly.
    $action = $this->getActionSetClear('field_node_single.target_id', $node2->id(), $defaults);
    $this->assertFalse($action->access($node1), 'User without permissions must not have access to change the field.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));
    // Create an action that sets the target of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('field_node_single.target_id', '[node2:nid]', $defaults);
    $this->assertTrue($action->access($node1), 'User with permissions must have access to change the field.');
    $this->assertEquals(NULL, $node1->field_node_single->target_id, 'Original field_node_single target before action execution must remain the same.');
    $this->assertEquals(NULL, $node2->field_node_single->target_id, 'Original field_node_single target before action execution must remain the same.');
    $action->execute($node1);
    $this->assertTrue(isset($node1->field_node_single->target_id), 'Reference target must be set.');
    $this->assertEquals($node2->id(), $node1->field_node_single->target_id, 'The target ID must match with the ID of node #2.');

    $action = $this->getActionSetClear('field_node_single', '', $defaults);
    $action->execute($node1);
    $this->assertTrue(!isset($node1->field_node_single->target_id), 'Reference target must not be set.');

    $new_node = $this->getNode('NEW', FALSE);
    $token_services->addTokenData('new_node', $new_node);

    $action = $this->getAction('set:empty', 'field_node_single', '[new_node]', $defaults);
    $this->assertTrue($new_node->isNew(), 'New node must not have been saved yet.');
    $action->execute($node1);
    $this->assertFalse($new_node->isNew(), 'New node must have been saved because the action is configured to save the entity in scope.');
    $this->assertTrue(isset($node1->field_node_single->target_id), 'Reference target must be set.');
    $this->assertEquals($new_node->id(), $node1->field_node_single->target_id, 'The target ID must match with the ID of new node.');

    $account_switcher->switchBack();
  }

  /**
   * Saves a field with a specific definition.
   *
   * @param string $name
   *   The name.
   * @param int $cardinality
   *   The cardinality.
   */
  private function saveField(string $name, int $cardinality): void {
    $field_definition = FieldStorageConfig::create([
      'field_name' => $name,
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => $cardinality,
    ]);
    $field_definition->save();
    $field = FieldConfig::create([
      'field_storage' => $field_definition,
      'label' => 'A single entity reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $field->save();
  }

  /**
   * Tests setting multiple references.
   */
  public function testNodeReferenceMulti() {
    $this->saveField('field_node_multi', FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $node1 = $this->getNode('123');
    $node2 = $this->getNode('456');
    $node3 = $this->getNode('999');

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $token_services->addTokenData('node1', $node1);
    $token_services->addTokenData('node2', $node2);
    $token_services->addTokenData('node3', $node3);
    $token_services->addTokenData('nodes', [$node2, $node3]);

    // Create an action that sets a target of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $defaults = [
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'save_entity' => TRUE,
    ];
    $action = $this->getActionSetClear('field_node_multi', $node2->id(), $defaults);
    $this->assertFalse($action->access($node1), 'User without permissions must not have access to change the field.');
    // Same as above, but using the "target_id" column explicitly.
    $action = $this->getActionSetClear('field_node_multi.target_id', $node2->id(), $defaults);
    $this->assertFalse($action->access($node1), 'User without permissions must not have access to change the field.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));

    // Create an action that sets the target of the node.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('field_node_multi.target_id', '[node2:nid]', $defaults);
    $this->assertTrue($action->access($node1), 'User with permissions must have access to change the field.');
    $this->assertEquals(NULL, $node1->field_node_multi->target_id, 'Original field_node_multi target before action execution must remain the same.');
    $this->assertEquals(NULL, $node2->field_node_multi->target_id, 'Original field_node_multi target before action execution must remain the same.');
    $action->execute($node1);
    $this->assertTrue(isset($node1->field_node_multi->target_id), 'Reference target must be set.');
    $this->assertEquals($node2->id(), $node1->field_node_multi->target_id, 'The target ID must match with the ID of node #2.');
    $this->assertCount(1, $node1->get('field_node_multi'), 'Exactly one item must be present in node1.');
    $this->assertCount(0, $node2->get('field_node_multi'), 'No item must be present in node2.');
    $this->assertCount(0, $node3->get('field_node_multi'), 'No item must be present in node3.');

    $action = $this->getActionSetClear('field_node_multi', '', $defaults);
    $action->execute($node1);
    $this->assertTrue(!isset($node1->field_node_multi->target_id), 'Reference target must not be set.');

    $new_node = $this->getNode('NEW', FALSE);
    $token_services->addTokenData('new_node', $new_node);

    $action = $this->getAction('set:empty', 'field_node_multi', '[new_node]', $defaults);
    $this->assertTrue($new_node->isNew(), 'New node must not have been saved yet.');
    $action->execute($node1);
    $this->assertFalse($new_node->isNew(), 'New node must have been saved because the action is configured to save the entity in scope.');
    $this->assertTrue(isset($node1->field_node_multi->target_id), 'Reference target must be set.');
    $this->assertEquals($new_node->id(), $node1->field_node_multi->target_id, 'The target ID must match with the ID of new node.');

    $action = $this->getAction('append:drop_first', 'field_node_multi.target_id', '[node2:nid]', $defaults);
    $action->execute($node1);
    $this->assertTrue(isset($node1->field_node_multi[0]), 'First item must be set.');
    $this->assertTrue(isset($node1->field_node_multi[1]), 'Second item must be set.');
    $this->assertTrue(!isset($node1->field_node_multi[2]), 'No third item must be set.');
    $this->assertEquals($new_node->id(), $node1->field_node_multi[0]->target_id, 'The target ID must match with the ID of new node.');
    $this->assertEquals($node2->id(), $node1->field_node_multi[1]->target_id, 'The target ID must match with the ID of node2.');

    $action = $this->getAction('prepend:drop_last', 'field_node_multi.target_id', '[node3]', $defaults);
    $action->execute($node1);
    $this->assertTrue(isset($node1->field_node_multi[0]), 'First item must be set.');
    $this->assertTrue(isset($node1->field_node_multi[1]), 'Second item must be set.');
    $this->assertTrue(isset($node1->field_node_multi[2]), 'Third item must be set.');
    $this->assertEquals($node3->id(), $node1->field_node_multi[0]->target_id, 'The target ID of the first item must match with the ID of node3.');
    $this->assertEquals($new_node->id(), $node1->field_node_multi[1]->target_id, 'The target ID of second item must match with the ID of new node.');
    $this->assertEquals($node2->id(), $node1->field_node_multi[2]->target_id, 'The target ID of third item must match with the ID of node2.');

    $action = $this->getActionSetClear('field_node_multi', '[nodes]', $defaults);
    $action->execute($node1);
    $this->assertTrue(isset($node1->field_node_multi[0]), 'First item must be set.');
    $this->assertTrue(isset($node1->field_node_multi[1]), 'Second item must be set.');
    $this->assertTrue(!isset($node1->field_node_multi[2]), 'Third item must not be set.');
    $this->assertEquals($node2->id(), $node1->field_node_multi[0]->target_id);
    $this->assertEquals($node3->id(), $node1->field_node_multi[1]->target_id);

    $account_switcher->switchBack();
  }

  /**
   * Tests setting field values on a node body field.
   */
  public function testNodeTitleForceClear() {
    $titleWithWhiteSpace = '  my title    ';
    $titleWithoutWhiteSpace = 'my title';

    $node = $this->getNode($titleWithWhiteSpace);

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    // Test that method "set:clear" does NOT work.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getActionSetClear('title', $titleWithoutWhiteSpace);
    $action->execute($node);
    $this->assertNotEquals($titleWithoutWhiteSpace, $node->getTitle(), 'After action execution, the title value is not trimmed.');

    // Test that method "set:force_clear" does work.
    /** @var \Drupal\eca_content\Plugin\Action\SetFieldValue $action */
    $action = $this->getAction('set:force_clear', 'title', $titleWithoutWhiteSpace);
    $action->execute($node);
    $this->assertEquals($titleWithoutWhiteSpace, $node->getTitle(), 'After action execution, the title value is trimmed.');
  }

  /**
   * Gets a node with given title.
   *
   * @param string $title
   *   The title.
   * @param bool $save
   *   If TRUE, then save, FALSE otherwise.
   *
   * @return \Drupal\node\Entity\NodeInterface
   *   The node.
   */
  private function getNode(string $title, bool $save = TRUE): NodeInterface {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'uid' => 0,
      'title' => $title,
    ]);
    if ($save) {
      $node->save();
    }
    return $node;
  }

  /**
   * Gets a node with body.
   *
   * @param string $title
   *   The title.
   * @param string $body
   *   The body.
   * @param string $summary
   *   The summary.
   *
   * @return \Drupal\node\Entity\NodeInterface
   *   The node.
   */
  private function getNodeWithBody(string $title, string $body, string $summary): NodeInterface {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'uid' => 0,
      'title' => $title,
      'body' => [
        [
          'value' => $body,
          'summary' => $summary,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Gets a node with field text multi.
   *
   * @param string $randomString
   *   A random string.
   * @param string $text
   *   The text.
   * @param string $summary
   *   The summary.
   *
   * @return \Drupal\node\Entity\NodeInterface
   *   The node.
   */
  private function getNodeWithTextMulti(string $randomString, string $text, string $summary): NodeInterface {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'uid' => 0,
      'title' => '123',
      'field_string_multi' => [$randomString, $randomString . '2', $randomString . '3'],
      'field_text_multi' => [
        [
          'value' => $text,
          'summary' => $summary,
          'format' => 'plain_text',
        ],
        [
          'value' => $text . '2',
          'summary' => $summary . '2',
          'format' => 'plain_text',
        ],
        [
          'value' => $text . '3',
          'summary' => $summary . '3',
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Gets the action Set Field Value.
   *
   * @param string $method
   *   The method.
   * @param string $fieldName
   *   The field name.
   * @param string $fieldValue
   *   The field value.
   * @param array $defaults
   *   The defaults.
   *
   * @return object
   *   The action.
   */
  private function getAction(
    string $method,
    string $fieldName,
    string $fieldValue,
    array $defaults = [
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'save_entity' => FALSE,
    ],
  ): object {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    return $action_manager->createInstance('eca_set_field_value', [
      'method' => $method,
      'field_name' => $fieldName,
      'field_value' => $fieldValue,
    ] + $defaults);
  }

  /**
   * Gets the action Set Field Value with the method set:clear.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $fieldValue
   *   The field value.
   * @param array $defaults
   *   The defaults.
   *
   * @return object
   *   The action.
   */
  private function getActionSetClear(
    string $fieldName,
    string $fieldValue,
    array $defaults = [
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'save_entity' => FALSE,
    ],
  ): object {
    return $this->getAction('set:clear', $fieldName, $fieldValue, $defaults);
  }

  /**
   * Asserts the first four values are set.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  private function firstFourValuesSet(NodeInterface $node): void {
    $this->assertTrue(isset($node->field_text_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_text_multi[1]), 'Second value must be set.');
    $this->assertTrue(isset($node->field_text_multi[2]), 'Third value must be set.');
    $this->assertTrue(isset($node->field_text_multi[3]), 'Fourth value must be set.');
  }

  /**
   * Asserts the first three string multi are set.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  private function firstThreeStringMultiSet(NodeInterface $node): void {
    $this->assertTrue(isset($node->field_string_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_string_multi[1]), 'Second value must be set.');
    $this->assertTrue(isset($node->field_string_multi[2]), 'Third value must be set.');
    $this->assertTrue(!isset($node->field_string_multi[3]), 'No fourth value must be set.');
  }

  /**
   * Asserts the first three text multi are set.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  private function firstThreeTextMultiSet(NodeInterface $node): void {
    $this->assertTrue(isset($node->field_text_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_text_multi[1]), 'Second value must be set.');
    $this->assertTrue(isset($node->field_text_multi[2]), 'Third value must be set.');
    $this->assertTrue(!isset($node->field_text_multi[3]), 'Fourth value must not be set.');
  }

  /**
   * Asserts for all fields.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $text
   *   The text.
   * @param string $summary
   *   The summary.
   * @param string $string
   *   The random string.
   */
  private function assertTextMultiWithSummary(NodeInterface $node, string $text, string $summary, string $string): void {
    $this->assertTrue(isset($node->field_text_multi[0]), 'First value must be set.');
    $this->assertTrue(isset($node->field_text_multi[1]), 'Second value must be set.');
    $this->assertTrue(isset($node->field_text_multi[2]), 'Third value must be set.');
    $this->assertTrue(isset($node->field_text_multi[3]), 'Fourth value must be set.');
    $this->assertTrue(isset($node->field_text_multi[4]), '5th value must be set.');
    $this->assertTrue(isset($node->field_text_multi[5]), '6th value must be set.');
    $this->assertTrue(!isset($node->field_text_multi[6]), '7th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[7]), '8th value must not be set.');
    $this->assertTrue(!isset($node->field_text_multi[8]), '9th value must not be set.');
    $this->assertEquals($text, $node->field_text_multi[0]->value, 'First value must remain unchanged.');
    $this->assertEquals($summary, $node->field_text_multi[0]->summary, 'First summary must remain unchanged.');
    $this->assertEquals($text . '2', $node->field_text_multi[1]->value, 'Second value must remain unchanged.');
    $this->assertEquals($summary . '2', $node->field_text_multi[1]->summary, 'Second summary must remain unchanged.');
    $this->assertEquals($text . '3', $node->field_text_multi[2]->value, 'Third value must remain unchanged.');
    $this->assertEquals($summary . '3', $node->field_text_multi[2]->summary, 'Third summary must remain unchanged.');
    $this->assertEquals($string, $node->field_text_multi[3]->value, 'Fourth value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[3]->summary, 'Fourth summary must remain unchanged.');
    $this->assertEquals($string . '2', $node->field_text_multi[4]->value, '5th value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[4]->summary, '5th summary must remain unchanged.');
    $this->assertEquals($string . '3', $node->field_text_multi[5]->value, '6th value must remain unchanged.');
    $this->assertEquals('', $node->field_text_multi[5]->summary, '6th summary must remain unchanged.');
  }

}
