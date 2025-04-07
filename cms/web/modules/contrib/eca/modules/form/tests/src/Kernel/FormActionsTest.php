<?php

namespace Drupal\Tests\eca_form\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_form\Event\FormBuild;
use Drupal\eca_form\Event\FormEvents;
use Drupal\eca_form\Event\FormProcess;
use Drupal\eca_form\Event\FormSubmit;
use Drupal\eca_form\Event\FormValidate;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Kernel tests regarding form actions.
 *
 * @group eca
 * @group eca_form
 */
class FormActionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'node',
    'eca',
    'eca_form',
  ];

  /**
   * Core action manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

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
    User::create(['uid' => 2, 'name' => 'auth'])->save();
    User::create(['uid' => 3, 'name' => 'somebody'])->save();

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
    // Create a single-value list field.
    FieldStorageConfig::create([
      'field_name' => 'field_selection',
      'type' => 'list_string',
      'entity_type' => 'node',
      'settings' => [
        'allowed_values' => ['key1' => 'Value 1', 'key2' => 'Value 2'],
      ],
      'module' => 'options',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_selection',
      'label' => 'Selection',
      'entity_type' => 'node',
      'bundle' => 'article',
      'default_value' => [['value' => 'key1']],
      'field_type' => 'list_string',
    ])->save();
    $form_display = EntityFormDisplay::load('node.article.default');
    $form_display->setComponent('field_selection', ['type' => 'options_select']);
    $form_display->save();

    $request = Request::create('/');
    $request->setSession(new Session());
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests the action plugin "eca_form_add_ajax".
   */
  public function testFormAddAjax(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddAjax $action */
    $action = $this->actionManager->createInstance('eca_form_add_ajax', [
      'disable_validation_errors' => TRUE,
      'validate_fields' => '',
      'field_name' => 'submit',
      'target' => '',
    ]);
    /** @var \Drupal\eca_form\Plugin\Action\FormAddAjax $action_not_existing */
    $action_not_existing = $this->actionManager->createInstance('eca_form_add_ajax', [
      'disable_validation_errors' => TRUE,
      'validate_fields' => '',
      'field_name' => 'not_existing',
      'target' => '',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $existing_access_result = NULL;
    $not_existing_access_result = NULL;
    $target_element = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$existing_access_result, &$not_existing_access_result, &$target_element, $action, $action_not_existing) {
      $action->setEvent($event);
      $action_not_existing->setEvent($event);
      $existing_access_result = $existing_access_result ?? $action->access(NULL);
      $not_existing_access_result = $action_not_existing->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $target_element = $event->getForm()['actions']['submit'] ?? NULL;
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($existing_access_result);
    $this->assertFalse($not_existing_access_result);
    $this->assertNotNull($target_element);
    $this->assertTrue(!empty($target_element['#ajax']));
    $this->assertTrue(isset($target_element['#limit_validation_errors']));
    $this->assertSame([], $target_element['#limit_validation_errors']);
  }

  /**
   * Tests the action plugin "eca_form_add_container_element".
   */
  public function testFormAddContainerElement(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddContainerElement $action */
    $action = $this->actionManager->createInstance('eca_form_add_container_element', [
      'name' => 'my][container',
      'optional' => FALSE,
      'weight' => '12',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $target_element = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$target_element, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $target_element = $event->getForm()['my']['container'] ?? NULL;
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertNotNull($target_element);
    $this->assertEquals('container', $target_element['#type']);
    $this->assertEquals('12', $target_element['#weight']);
  }

  /**
   * Tests the action plugin "eca_form_add_group_element".
   */
  public function testFormAddGroupElement(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddGroupElement $action */
    $action = $this->actionManager->createInstance('eca_form_add_group_element', [
      'name' => 'my_group',
      'title' => 'Group title',
      'open' => TRUE,
      'weight' => '99',
      'fields' => 'title, body',
      'introduction_text' => 'An introduction text.',
      'summary_value' => 'A summary value.',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['my_group']['#type']));
    $this->assertEquals('details', $form['my_group']['#type']);
    $this->assertEquals('99', $form['my_group']['#weight']);
    $this->assertEquals('An introduction text.', $form['my_group']['introduction_text']['#markup']);
    $this->assertEquals('Group title', $form['my_group']['#title']);
    $this->assertEquals('A summary value.', $form['my_group']['#value']);
    $this->assertEquals('my_group', $form['title']['#group']);
    $this->assertEquals('my_group', $form['body']['#group']);
  }

  /**
   * Tests the action plugin "eca_form_add_hiddenfield".
   */
  public function testFormAddHiddenField(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddHiddenField $action */
    $action = $this->actionManager->createInstance('eca_form_add_hiddenfield', [
      'name' => 'myhidden_field',
      'value' => 'hidden_value',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['myhidden_field']));
    $this->assertEquals('hidden_value', $form['myhidden_field']['#value']);
  }

  /**
   * Tests the action plugin "eca_form_add_optionsfield".
   */
  public function testFormAddOptionsField(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddOptionsField $action */
    $action = $this->actionManager->createInstance('eca_form_add_optionsfield', [
      'name' => 'myoptions',
      'type' => 'select',
      'multiple' => FALSE,
      'options' => 'option1, option2',
      'use_yaml' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['myoptions']));
    $this->assertEquals('select', $form['myoptions']['#type']);
    $this->assertSame([
      'option1' => 'option1',
      'option2' => 'option2',
    ], $form['myoptions']['#options']);
  }

  /**
   * Tests the action plugin "eca_form_add_optionsfield" using checkboxes.
   */
  public function testFormAddCheckboxes(): void {
    $users = [User::load(0), User::load(1), User::load(2)];
    $this->tokenService->addTokenData('users', $users);

    /** @var \Drupal\eca_form\Plugin\Action\FormAddOptionsField $action */
    $action = $this->actionManager->createInstance('eca_form_add_optionsfield', [
      'name' => 'mycheckboxes',
      'type' => 'checkboxes',
      'multiple' => TRUE,
      'options' => '[users]',
      'default_value' => '1',
      'use_yaml' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $build = $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['mycheckboxes']));
    $this->assertEquals('checkboxes', $form['mycheckboxes']['#type']);
    $this->assertSame([
      '0' => User::load(0)->label(),
      '1' => User::load(1)->label(),
      '2' => User::load(2)->label(),
    ], $form['mycheckboxes']['#options']);

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $rendered = $renderer->renderInIsolation($build);
    $this->assertStringContainsString('name="mycheckboxes[0]" value="0"', $rendered);
    $this->assertStringNotContainsString('name="mycheckboxes[0]" value="0" checked="checked"', $rendered);
    $this->assertStringContainsString('name="mycheckboxes[1]" value="1" checked="checked"', $rendered);
    $this->assertStringContainsString('name="mycheckboxes[2]" value="2"', $rendered);
    $this->assertStringNotContainsString('name="mycheckboxes[2]" value="2" checked="checked"', $rendered);
  }

  /**
   * Tests the action plugin "eca_form_add_optionsfield" using checkboxes.
   *
   * Default values for the checkboxes are entities.
   */
  public function testFormAddCheckboxesDefaultValueEntities(): void {
    $users = [User::load(1), User::load(2), User::load(3)];
    $defaultValues = [
      1 => '1',
      2 => '2',
      3 => '3',
    ];
    $this->tokenService->addTokenData('users', $users);
    $this->tokenService->addTokenData('default_values', $defaultValues);

    /** @var \Drupal\eca_form\Plugin\Action\FormAddOptionsField $action */
    $action = $this->actionManager->createInstance('eca_form_add_optionsfield', [
      'name' => 'mycheckboxes',
      'type' => 'checkboxes',
      'multiple' => TRUE,
      'options' => '[users]',
      'default_value' => '[default_values]',
      'use_yaml' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $build = $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['mycheckboxes']));
    $this->assertEquals('checkboxes', $form['mycheckboxes']['#type']);
    $this->assertSame([
      '1' => User::load(1)->label(),
      '2' => User::load(2)->label(),
      '3' => User::load(3)->label(),
    ], $form['mycheckboxes']['#options']);

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $rendered = $renderer->renderInIsolation($build);
    $this->assertStringContainsString('name="mycheckboxes[1]" value="1" checked="checked"', $rendered);
    $this->assertStringContainsString('name="mycheckboxes[2]" value="2" checked="checked"', $rendered);
    $this->assertStringContainsString('name="mycheckboxes[3]" value="3" checked="checked"', $rendered);
  }

  /**
   * Tests the action plugin "eca_form_add_submit_button".
   */
  public function testFormAddSubmitButton(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddSubmitButton $action */
    $action = $this->actionManager->createInstance('eca_form_add_submit_button', [
      'name' => 'custom_send',
      'value' => 'Send',
      'weight' => '15',
      'button_type' => 'primary',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['actions']['custom_send']));
    $this->assertEquals('submit', $form['actions']['custom_send']['#type']);
    $this->assertEquals('15', $form['actions']['custom_send']['#weight']);
    $this->assertEquals('primary', $form['actions']['custom_send']['#button_type']);
    $this->assertEquals('Send', $form['actions']['custom_send']['#value']);
  }

  /**
   * Tests the action plugin "eca_form_add_textfield".
   */
  public function testFormAddTextfield(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormAddTextfield $action */
    $action = $this->actionManager->createInstance('eca_form_add_textfield', [
      'type' => 'textfield',
      'name' => 'my[custom_textfield]',
      'title' => 'My custom textfield',
      'description' => 'This is my custom text field.',
      'required' => TRUE,
      'weight' => '29',
      'default_value' => 'Default text.',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['my']['custom_textfield']));
    $this->assertEquals('textfield', $form['my']['custom_textfield']['#type']);
    $this->assertEquals('29', $form['my']['custom_textfield']['#weight']);
    $this->assertTrue($form['my']['custom_textfield']['#required']);
    $this->assertEquals('My custom textfield', $form['my']['custom_textfield']['#title']);
    $this->assertEquals('This is my custom text field.', $form['my']['custom_textfield']['#description']);
    $this->assertEquals('Default text.', $form['my']['custom_textfield']['#default_value']);
  }

  /**
   * Tests the action plugin "eca_form_build_entity".
   */
  public function testFormBuildEntity(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormBuildEntity $action */
    $action = $this->actionManager->createInstance('eca_form_build_entity', [
      'token_name' => 'mynode',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $build_access_result = NULL;
    $build_node = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, function (FormBuild $event) use (&$build_access_result, &$build_node, $action) {
      $action->setEvent($event);
      $build_access_result = $build_access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $build_node = $this->tokenService->getTokenData('mynode');
    });

    $process_access_result = NULL;
    $process_node = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$process_access_result, &$process_node, $action) {
      $action->setEvent($event);
      $process_access_result = $process_access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $process_node = $this->tokenService->getTokenData('mynode');
    });

    $validate_access_result = NULL;
    $validate_node = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, function (FormValidate $event) use (&$validate_access_result, &$validate_node, $action) {
      $action->setEvent($event);
      $validate_access_result = $validate_access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $validate_node = $this->tokenService->getTokenData('mynode');
    });

    $submit_access_result = NULL;
    $submit_node = NULL;
    $event_dispatcher->addListener(FormEvents::SUBMIT, function (FormSubmit $event) use (&$submit_access_result, &$submit_node, $action) {
      $action->setEvent($event);
      $submit_access_result = $submit_access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $submit_node = $this->tokenService->getTokenData('mynode');
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => 'Original title',
      'body' => [['value' => 'Original body value']],
      'field_string_multi' => [['value' => 'Original string multi value']],
    ]));
    $form_state = new FormState();
    $title_value = 'Changed title';
    $body_value = 'Changed body value';
    $field_string_multi_value = 'Changed string multi value';
    $form_builder->buildForm($form_object, $form_state);
    $form_state->setValues([
      'title' => [['value' => $title_value]],
      'body' => [['value' => $body_value]],
      'field_string_multi' => [['value' => $field_string_multi_value]],
    ] + $form_state->getValues());
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($build_access_result, "Building the entity must be allowed when working on an entity form, even when the for was not submitted yet.");
    $this->assertTrue($build_node instanceof NodeInterface);
    /** @var \Drupal\node\NodeInterface $build_node */
    $this->assertEquals($title_value, $build_node->label());
    $this->assertEquals($body_value, $build_node->body->value);
    $this->assertEquals('Original string multi value', $build_node->field_string_multi->value, "The original value must remain the same, because field_string_multi is not shown in the default node form.");

    $this->assertTrue($process_node instanceof NodeInterface);
    /** @var \Drupal\node\NodeInterface $process_node */
    $this->assertEquals($title_value, $process_node->label());
    $this->assertEquals($body_value, $process_node->body->value);
    $this->assertEquals('Original string multi value', $process_node->field_string_multi->value, "The original value must remain the same, because field_string_multi is not shown in the default node form.");

    $this->assertTrue($validate_node instanceof NodeInterface);
    /** @var \Drupal\node\NodeInterface $validate_node */
    $this->assertEquals($title_value, $validate_node->label());
    $this->assertEquals($body_value, $validate_node->body->value);
    $this->assertEquals('Original string multi value', $validate_node->field_string_multi->value, "The original value must remain the same, because field_string_multi is not shown in the default node form.");

    $this->assertTrue($submit_node instanceof NodeInterface);
    /** @var \Drupal\node\NodeInterface $submit_node */
    $this->assertEquals($title_value, $submit_node->label());
    $this->assertEquals($body_value, $submit_node->body->value);
    $this->assertEquals('Original string multi value', $submit_node->field_string_multi->value, "The original value must remain after submission, because field_string_multi is not shown in the default node form, and is being filtered out during validation.");
  }

  /**
   * Tests the action plugin "eca_form_field_access".
   */
  public function testFormFieldAccess(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldAccess $action */
    $action = $this->actionManager->createInstance('eca_form_field_access', [
      'field_name' => 'title',
      'flag' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['title']['widget'][0]['value']['#access']));
    $this->assertFalse($form['title']['widget'][0]['value']['#access']);
  }

  /**
   * Tests the action plugin "eca_form_field_default_value".
   */
  public function testFormFieldDefaultValue(): void {
    // Create a reference field.
    $field_definition = FieldStorageConfig::create([
      'field_name' => 'field_node_ref',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_definition->save();
    $field = FieldConfig::create([
      'field_storage' => $field_definition,
      'label' => 'A node reference.',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $form_display = $display_repository->getFormDisplay('node', 'article');
    $form_display->setComponent('field_node_ref', [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
      ],
    ]);
    $form_display->save();

    /** @var \Drupal\eca_form\Plugin\Action\FormFieldDefaultValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_default_value', [
      'value' => 'Default title value',
      'field_name' => 'title',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'xss_filter' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, &$action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['title']['widget'][0]['value']['#default_value']));
    $this->assertEquals('Default title value', $form['title']['widget'][0]['value']['#default_value']);

    // Test the entity autocomplete field using the node reference field above.
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldDefaultValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_default_value', [
      'value' => '[node1]',
      'field_name' => 'field_node_ref',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'xss_filter' => FALSE,
    ]);

    $node1 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $node1->save();
    $this->tokenService->addTokenData('node1', $node1);

    $node2 = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $this->tokenService->addTokenData('node2', $node2);

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node2);
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertFalse(isset($form['field_node_ref']['widget'][0]['target_id']['#default_value']), "Anonymous user must not have access to reference to inaccessible items.");

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node2);
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['field_node_ref']['widget'][0]['target_id']['#default_value']) && $form['field_node_ref']['widget'][0]['target_id']['#default_value'] instanceof NodeInterface);
    $this->assertSame($node1->id(), $form['field_node_ref']['widget'][0]['target_id']['#default_value']->id());

    // Test the datetime field using the "created" base field of the node.
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldDefaultValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_default_value', [
      'value' => '1657884192976',
      'field_name' => 'created',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'xss_filter' => FALSE,
    ]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node1);
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['created']['widget'][0]['value']['#default_value']) && $form['created']['widget'][0]['value']['#default_value'] instanceof DrupalDateTime);
    $this->assertSame(1657884192976, $form['created']['widget'][0]['value']['#default_value']->getTimestamp());

    // Test renaming the default submit button.
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldDefaultValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_default_value', [
      'value' => 'Renamed',
      'field_name' => 'submit',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'xss_filter' => FALSE,
    ]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($node1);
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['actions']['submit']['#value']));
    $this->assertSame("Renamed", $form['actions']['submit']['#value']);
  }

  /**
   * Tests the action plugin "eca_form_field_get_default_value".
   */
  public function testFormFieldGetDefaultValue(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldGetDefaultValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_get_default_value', [
      'field_name' => 'field_selection',
      'token_name' => 'default',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'field_selection' => ['key2'],
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertSame(['key2'], $this->tokenService->getTokenData('default')->toArray());
  }

  /**
   * Tests the action plugin "eca_form_field_disable".
   */
  public function testFormFieldDisable(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldDisable $action */
    $action = $this->actionManager->createInstance('eca_form_field_disable', [
      'field_name' => 'title',
      'flag' => TRUE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['title']['widget'][0]['value']['#disabled']));
    $this->assertTrue($form['title']['widget'][0]['value']['#disabled']);
  }

  /**
   * Tests the action plugin "eca_form_field_get_value".
   */
  public function testFormFieldGetValue(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldGetValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_get_value', [
      'field_name' => 'title',
      'token_name' => 'submitted_title',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'xss_filter' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $token_value = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, function (FormValidate $event) use (&$access_result, &$token_value, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $token_value = $this->tokenService->replaceClear('[submitted_title:0:value]');
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => 'Original title',
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_state->setValues([
      'title' => [['value' => 'Changed title']],
    ] + $form_state->getValues());
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertEquals('Changed title', $token_value);
  }

  /**
   * Tests the action plugin "eca_form_field_require".
   */
  public function testFormFieldRequire(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldRequire $action */
    $action = $this->actionManager->createInstance('eca_form_field_require', [
      'field_name' => 'body',
      'flag' => TRUE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['body']['widget'][0]['#required']));
    $this->assertTrue($form['body']['widget'][0]['#required']);
  }

  /**
   * Tests the action plugin "eca_form_field_set_error".
   */
  public function testFormFieldSetError(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldSetError $action */
    $action = $this->actionManager->createInstance('eca_form_field_set_error', [
      'field_name' => 'title',
      'message' => 'Custom title error',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, function (FormValidate $event) use (&$access_result, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue($form_state->hasAnyErrors());
    $errors = $form_state->getErrors();
    $this->assertTrue(isset($errors['title][0][value']));
    $this->assertEquals('Custom title error', $errors['title][0][value']);
  }

  /**
   * Tests the action plugin "eca_form_field_get_options".
   */
  public function testFormFieldGetOptions(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldGetOptions $action */
    $action = $this->actionManager->createInstance('eca_form_field_get_options', [
      'field_name' => 'field_selection',
      'token_name' => 'options',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'field_selection' => ['key2'],
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertSame([
      '_none' => '- None -',
      'key1' => 'Value 1',
      'key2' => 'Value 2',
    ], $this->tokenService->getTokenData('options')->toArray());
  }

  /**
   * Tests the action plugin "eca_form_field_set_options".
   */
  public function testFormFieldSetOptions(): void {
    $options = <<<YAML
custom1: Custom One
custom2: Custom Two
key1: Value One
YAML;
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldSetOptions $action */
    $action = $this->actionManager->createInstance('eca_form_field_set_options', [
      'field_name' => 'field_selection',
      'options' => $options,
      'use_yaml' => TRUE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $form = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$access_result, &$form, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue(isset($form['field_selection']['widget']['#options']));
    $this->assertSame([
      'custom1' => 'Custom One',
      'custom2' => 'Custom Two',
      'key1' => 'Value One',
    ], $form['field_selection']['widget']['#options']);
  }

  /**
   * Tests the action plugin "eca_form_field_set_value".
   */
  public function testFormFieldSetValue(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormFieldSetValue $action */
    $action = $this->actionManager->createInstance('eca_form_field_set_value', [
      'field_name' => 'custom_value',
      'field_value' => 'Automatically set value',
      'use_yaml' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, function (FormValidate $event) use (&$access_result, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => 'Original title',
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_state->setUserInput([
      'custom_value' => 'Should get overwritten...',
    ] + $form_state->getUserInput());
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertEquals('Automatically set value', $form_state->getValue('custom_value'));
  }

  /**
   * Tests the action plugin "eca_form_get_errors".
   */
  public function testFormGetErrors(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormGetErrors $action */
    $action = $this->actionManager->createInstance('eca_form_get_errors', [
      'token_name' => 'loaded_errors',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $event_dispatcher->addListener(FormEvents::VALIDATE, function (FormValidate $event) use (&$access_result, $action) {
      $event->getFormState()->setErrorByName('title', 'Here is an error.');
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertTrue($form_state->hasAnyErrors());
    $errors = $form_state->getErrors();
    $this->assertTrue(isset($errors['title']));
    $this->assertEquals('Here is an error.', $errors['title']);
    $this->assertEquals($errors['title'], $this->tokenService->replaceClear('[loaded_errors:title]'));
  }

  /**
   * Tests the action plugin "eca_form_state_get_property_value".
   */
  public function testFormStateGetPropertyValue(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormStateGetPropertyValue $action */
    $action = $this->actionManager->createInstance('eca_form_state_get_property_value', [
      'property_name' => 'someprop',
      'token_name' => 'fs_property',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, function (FormBuild $event) use (&$access_result, $action) {
      $event->getFormState()->set(['eca', 'someprop'], 'Hello from form state!');
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertEquals('Hello from form state!', $this->tokenService->replaceClear('[fs_property]'));
  }

  /**
   * Tests the action plugin "eca_form_state_set_property_value".
   */
  public function testFormStateSetPropertyValue(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormStateSetPropertyValue $action */
    $action = $this->actionManager->createInstance('eca_form_state_set_property_value', [
      'property_name' => 'someprop',
      'property_value' => 'Hello from the outside!',
      'use_yaml' => FALSE,
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $event_dispatcher->addListener(FormEvents::BUILD, function (FormBuild $event) use (&$access_result, $action) {
      $event->getFormState()->set(['eca', 'someprop'], 'Hello from form state!');
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertTrue($access_result);
    $this->assertEquals('Hello from the outside!',
      $form_state->get(['eca', 'someprop']));
  }

  /**
   * Tests the action plugin "eca_form_state_set_redirect".
   */
  public function testFormStateSetRedirect(): void {
    /** @var \Drupal\eca_form\Plugin\Action\FormStateSetRedirect $action */
    $action = $this->actionManager->createInstance('eca_form_state_set_redirect', [
      'destination' => '/admin/structure',
    ]);

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $access_result = NULL;
    $event_dispatcher->addListener(FormEvents::SUBMIT, function (FormSubmit $event) use (&$access_result, $action) {
      $action->setEvent($event);
      $access_result = $access_result ?? $action->access(NULL);
      if ($action->access(NULL)) {
        $action->execute();
      }
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity(Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]));
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
    $form_builder->submitForm($form_object, $form_state);

    $redirect = (function () {
      return $this->redirect ?? NULL;
    })(...)->call($form_state);
    $this->assertInstanceOf(Url::class, $redirect);
    /** @var \Drupal\Core\Url $redirect */
    $this->assertSame("/admin/structure", $redirect->toString());
  }

}
