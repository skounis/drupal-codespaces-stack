<?php

namespace Drupal\Tests\eca_form\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca_form\Event\FormBuild;
use Drupal\eca_form\Event\FormEvents;
use Drupal\eca_form\Event\FormProcess;
use Drupal\eca_form\Event\FormSubmit;
use Drupal\eca_form\Event\FormValidate;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Kernel tests regarding form conditions.
 *
 * @group eca
 * @group eca_form
 */
class FormConditionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_form',
  ];

  /**
   * ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);
    // Create a multi-value text field.
    FieldStorageConfig::create([
      'field_name' => 'field_string_multi',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_string_multi',
      'label' => 'A string field having multiple values.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $request = Request::create('/');
    $request->setSession(new Session());
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);

    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
  }

  /**
   * Tests the condition plugin "eca_form_field_exists".
   */
  public function testFormFieldExists(): void {
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormFieldExists $true_condition */
    $true_condition = $this->conditionManager->createInstance('eca_form_field_exists', [
      'field_name' => 'body',
      'negate' => FALSE,
    ]);
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormFieldExists $false_condition */
    $false_condition = $this->conditionManager->createInstance('eca_form_field_exists', [
      'field_name' => 'not_existing',
      'negate' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $build_true_result = NULL;
    $build_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, static function (FormBuild $event) use (&$build_true_result, &$build_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $build_true_result = $true_condition->evaluate();
      $build_false_result = $false_condition->evaluate();
    });

    $process_true_result = NULL;
    $process_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, static function (FormProcess $event) use (&$process_true_result, &$process_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $process_true_result = $true_condition->evaluate();
      $process_false_result = $false_condition->evaluate();
    });

    $validate_true_result = NULL;
    $validate_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, static function (FormValidate $event) use (&$validate_true_result, &$validate_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $validate_true_result = $true_condition->evaluate();
      $validate_false_result = $false_condition->evaluate();
    });

    $submit_true_result = NULL;
    $submit_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::SUBMIT, static function (FormSubmit $event) use (&$submit_true_result, &$submit_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $submit_true_result = $true_condition->evaluate();
      $submit_false_result = $false_condition->evaluate();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($build_true_result);
    $this->assertFalse($build_false_result);
    $this->assertTrue($process_true_result);
    $this->assertFalse($process_false_result);
    $this->assertTrue($validate_true_result);
    $this->assertFalse($validate_false_result);
    $this->assertTrue($submit_true_result);
    $this->assertFalse($submit_false_result);
  }

  /**
   * Tests the condition plugin "eca_form_field_value".
   */
  public function testFormFieldValue(): void {
    $config = [
      'field_name' => 'test_field',
      'field_value' => 'Test value',
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_form_field_value', $config);
    $form_state = new FormState();
    $form_state->setValue('test_field', 'Test value');
    $form = [];
    $event = new FormBuild($form, $form_state, 'test_id');
    $condition->setEvent($event);
    $this->assertTrue($condition->evaluate(), 'Value of form field "test_field" equals expected value.');
    $form_state->setValue('test_field', 'Another value');
    $this->assertFalse($condition->evaluate(), 'Different value must not evaluate to TRUE.');

    // Simulate submission of a list of checked and un-checked checkboxes.
    $form_state->setValue(['test', 'field_list'], [0, '1', 0, '2', 0, 0]);
    $config = [
      'field_name' => 'test.field_list',
      'field_value' => '',
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_form_field_value', $config);
    $condition->setEvent($event);
    $this->assertFalse($condition->evaluate(), 'The submitted list is not empty.');
    $condition->setConfiguration(['negate' => TRUE] + $condition->getConfiguration());
    $this->assertTrue($condition->evaluate(), 'The submitted list is not empty.');

    $config = [
      'field_name' => 'test[field_list]',
      'field_value' => '2',
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_form_field_value', $config);
    $condition->setEvent($event);
    $this->assertTrue($condition->evaluate(), 'The submitted value "2" is checked in the list.');
    $condition->setConfiguration([
      'field_name' => 'test][field_list',
      'field_value' => '2',
    ] + $condition->getConfiguration());
    $this->assertTrue($condition->reset()->evaluate(), 'The submitted value "2" is checked in the list.');
    $condition->setConfiguration([
      'field_name' => 'test][field_list',
      'field_value' => '3',
    ] + $condition->getConfiguration());
    $this->assertFalse($condition->reset()->evaluate(), 'The submitted value "3" is not checked in the list.');
  }

  /**
   * Tests the condition plugin "eca_form_has_errors".
   */
  public function testFormHasErrors(): void {
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormHasErrors $condition */
    $condition = $this->conditionManager->createInstance('eca_form_has_errors', [
      'negate' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $build_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, static function (FormBuild $event) use (&$build_result, $condition) {
      $condition->setEvent($event);
      $build_result = $condition->evaluate();
    });

    $process_result = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, static function (FormProcess $event) use (&$process_result, $condition) {
      $condition->setEvent($event);
      $process_result = $condition->evaluate();
    });

    $validate_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, static function (FormValidate $event) use (&$validate_result, $condition) {
      $condition->setEvent($event);
      $validate_result = $condition->evaluate();
    });

    $submit_result = NULL;
    $event_dispatcher->addListener(FormEvents::SUBMIT, static function (FormSubmit $event) use (&$submit_result, $condition) {
      $condition->setEvent($event);
      $submit_result = $condition->evaluate();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertFalse($build_result);
    $this->assertFalse($process_result);
    $this->assertTrue($validate_result);
    $this->assertNull($submit_result);

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertFalse($build_result);
    $this->assertFalse($process_result);
    $this->assertFalse($validate_result);
    $this->assertFalse($submit_result);
  }

  /**
   * Tests the condition plugin "eca_form_has_errors".
   */
  public function testFormOperation(): void {
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormOperation $true_condition */
    $true_condition = $this->conditionManager->createInstance('eca_form_operation', [
      'operation' => 'default',
      'negate' => FALSE,
    ]);
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormOperation $false_condition */
    $false_condition = $this->conditionManager->createInstance('eca_form_operation', [
      'operation' => 'edit',
      'negate' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $true_build_result = NULL;
    $false_build_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, static function (FormBuild $event) use (&$true_build_result, &$false_build_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $true_build_result = $true_condition->evaluate();
      $false_build_result = $false_condition->evaluate();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($true_build_result);
    $this->assertFalse($false_build_result);
  }

  /**
   * Tests the condition plugin "eca_form_submitted".
   */
  public function testFormSubmitted(): void {
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormSubmitted $condition */
    $condition = $this->conditionManager->createInstance('eca_form_submitted', [
      'negate' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $build_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, static function (FormBuild $event) use (&$build_result, $condition) {
      $condition->setEvent($event);
      $build_result = $build_result ?? $condition->evaluate();
    });

    $process_result = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, static function (FormProcess $event) use (&$process_result, $condition) {
      $condition->setEvent($event);
      $process_result = $process_result ?? $condition->evaluate();
    });

    $validate_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, static function (FormValidate $event) use (&$validate_result, $condition) {
      $condition->setEvent($event);
      $validate_result = $condition->evaluate();
    });

    $submit_result = NULL;
    $event_dispatcher->addListener(FormEvents::SUBMIT, static function (FormSubmit $event) use (&$submit_result, $condition) {
      $condition->setEvent($event);
      $submit_result = $condition->evaluate();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertFalse($build_result);
    $this->assertFalse($process_result);
    $this->assertTrue($validate_result);
    $this->assertTrue($submit_result);
  }

  /**
   * Tests the condition plugin "eca_form_triggered".
   */
  public function testFormTriggered(): void {
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormTriggered $true_condition */
    $true_condition = $this->conditionManager->createInstance('eca_form_triggered', [
      'trigger_name' => 'submit',
      'negate' => FALSE,
    ]);
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormTriggered $false_condition */
    $false_condition = $this->conditionManager->createInstance('eca_form_triggered', [
      'trigger_name' => 'something',
      'negate' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $build_true_result = NULL;
    $build_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, static function (FormBuild $event) use (&$build_true_result, &$build_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $build_true_result = $true_condition->evaluate();
      $build_false_result = $false_condition->evaluate();
    });

    $process_true_result = NULL;
    $process_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, static function (FormProcess $event) use (&$process_true_result, &$process_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $process_true_result = $true_condition->evaluate();
      $process_false_result = $false_condition->evaluate();
    });

    $validate_true_result = NULL;
    $validate_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, static function (FormValidate $event) use (&$validate_true_result, &$validate_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $validate_true_result = $true_condition->evaluate();
      $validate_false_result = $false_condition->evaluate();
    });

    $submit_true_result = NULL;
    $submit_false_result = NULL;
    $event_dispatcher->addListener(FormEvents::SUBMIT, static function (FormSubmit $event) use (&$submit_true_result, &$submit_false_result, $true_condition, $false_condition) {
      $true_condition->setEvent($event);
      $false_condition->setEvent($event);
      $submit_true_result = $true_condition->evaluate();
      $submit_false_result = $false_condition->evaluate();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form = $form_builder->buildForm($form_object, $form_state);
    $form_state->setTriggeringElement($form['actions']['submit']);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($build_true_result);
    $this->assertFalse($build_false_result);
    $this->assertTrue($process_true_result);
    $this->assertFalse($process_false_result);
    $this->assertTrue($validate_true_result);
    $this->assertFalse($validate_false_result);
    $this->assertTrue($submit_true_result);
    $this->assertFalse($submit_false_result);
  }

}
