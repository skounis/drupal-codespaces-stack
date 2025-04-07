<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Execution chain tests using plugins of eca_form.
 *
 * @group eca
 * @group eca_form
 */
class FormExecutionChainTest extends KernelTestBase {

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
    'eca',
    'eca_form',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'pass' => '123',
    ])->save();

    $request = Request::create('/');
    $request->setSession(new Session());
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);
  }

  /**
   * Tests an execution chain setting form state property values.
   */
  public function testFormPropertyValues(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    // This config does the following:
    // 1. Reacts upon commonly supported form events (build, validate, submit).
    // 2. Sets property values on a form state in different formats.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'form_process',
      'label' => 'ECA form process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'event_form_build' => [
          'plugin' => 'form:form_build',
          'label' => 'Building form',
          'configuration' => [
            'form_id' => 'user_form',
          ],
          'successors' => [
            [
              'id' => 'action_form_state_set_key1',
              'condition' => '',
            ],
          ],
        ],
        'event_form_process' => [
          'plugin' => 'form:form_process',
          'label' => 'After building form',
          'configuration' => [
            'form_id' => 'user_form',
          ],
          'successors' => [
            [
              'id' => 'action_form_state_set_key4',
              'condition' => '',
            ],
          ],
        ],
        'event_form_after_build' => [
          'plugin' => 'form:form_after_build',
          'label' => 'After building form',
          'configuration' => [
            'form_id' => 'user_form',
          ],
          'successors' => [
            [
              'id' => 'action_form_state_set_key5',
              'condition' => '',
            ],
          ],
        ],
        'event_form_validate' => [
          'plugin' => 'form:form_validate',
          'label' => 'Validating form',
          'configuration' => [
            'form_id' => 'user_form',
          ],
          'successors' => [
            [
              'id' => 'action_form_state_set_key2',
              'condition' => '',
            ],
          ],
        ],
        'event_form_submit' => [
          'plugin' => 'form:form_submit',
          'label' => 'Submitting form',
          'configuration' => [
            'form_id' => 'user_form',
          ],
          'successors' => [
            [
              'id' => 'action_form_state_set_key3',
              'condition' => '',
            ],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'action_form_state_set_key1' => [
          'plugin' => 'eca_form_state_set_property_value',
          'label' => 'Set key1 in form state as property value',
          'configuration' => [
            'property_name' => 'key1',
            'property_value' => 'Value of key1',
            'use_yaml' => FALSE,
          ],
          'successors' => [
            [
              'id' => 'action_form_state_get_key1',
              'condition' => '',
            ],
          ],
        ],
        'action_form_state_get_key1' => [
          'plugin' => 'eca_form_state_get_property_value',
          'label' => 'Get key1 in form state as property value',
          'configuration' => [
            'property_name' => 'key1',
            'token_name' => 'formstate:key1',
          ],
          'successors' => [],
        ],
        'action_form_state_set_key2' => [
          'plugin' => 'eca_form_state_set_property_value',
          'label' => 'Set nested key2 in form state as property value',
          'configuration' => [
            'property_name' => 'key2',
            'property_value' => 'nested: "Value of key2"',
            'use_yaml' => TRUE,
          ],
          'successors' => [
            [
              'id' => 'action_form_state_get_key2',
              'condition' => '',
            ],
          ],
        ],
        'action_form_state_get_key2' => [
          'plugin' => 'eca_form_state_get_property_value',
          'label' => 'Get nested key2 in form state as property value',
          'configuration' => [
            'property_name' => 'key2',
            'token_name' => 'formstate:key2',
          ],
          'successors' => [],
        ],
        'action_form_state_set_key3' => [
          'plugin' => 'eca_form_state_set_property_value',
          'label' => 'Set key3 in form state as property value',
          'configuration' => [
            'property_name' => 'key3',
            'property_value' => 'Value of key3',
            'use_yaml' => FALSE,
          ],
          'successors' => [
            [
              'id' => 'action_form_state_get_key3',
              'condition' => '',
            ],
          ],
        ],
        'action_form_state_get_key3' => [
          'plugin' => 'eca_form_state_get_property_value',
          'label' => 'Get key3 in form state as property value',
          'configuration' => [
            'property_name' => 'key3',
            'token_name' => 'formstate:key3',
          ],
          'successors' => [],
        ],
        'action_form_state_set_key4' => [
          'plugin' => 'eca_form_state_set_property_value',
          'label' => 'Set key4 in form state as property value',
          'configuration' => [
            'property_name' => 'key4',
            'property_value' => 'Value of key4',
            'use_yaml' => FALSE,
          ],
          'successors' => [
            [
              'id' => 'action_form_state_get_key4',
              'condition' => '',
            ],
          ],
        ],
        'action_form_state_get_key4' => [
          'plugin' => 'eca_form_state_get_property_value',
          'label' => 'Get key4 in form state as property value',
          'configuration' => [
            'property_name' => 'key4',
            'token_name' => 'formstate:key4',
          ],
          'successors' => [],
        ],
        'action_form_state_set_key5' => [
          'plugin' => 'eca_form_state_set_property_value',
          'label' => 'Set key5 in form state as property value',
          'configuration' => [
            'property_name' => 'key5',
            'property_value' => 'Value of key5',
            'use_yaml' => FALSE,
          ],
          'successors' => [
            [
              'id' => 'action_form_state_get_key5',
              'condition' => '',
            ],
          ],
        ],
        'action_form_state_get_key5' => [
          'plugin' => 'eca_form_state_get_property_value',
          'label' => 'Get key5 in form state as property value',
          'configuration' => [
            'property_name' => 'key5',
            'token_name' => 'formstate:key5',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $admin_user = User::load(1);

    // Switch to privileged account.
    $account_switcher->switchTo($admin_user);

    $form_object = \Drupal::entityTypeManager()->getFormObject('user', 'default');
    $form_object->setEntity($admin_user);
    $form_state = (new FormState())
      ->setFormObject($form_object);
    $form_builder = \Drupal::formBuilder();
    $form_builder->buildForm($form_object, $form_state);
    $form_state->setValues([
      'name' => 'superadmin',
      'mail' => 'superadmin@examplesite.local',
      'current_pass' => '123',
      'roles' => [],
    ]);
    $form_builder->submitForm($form_object, $form_state);

    $this->assertEquals('Value of key1', $form_state->get([
      'eca',
      'key1',
    ]));
    $this->assertEquals('Value of key2', $form_state->get([
      'eca',
      'key2',
      'nested',
    ]));
    $this->assertEquals('Value of key3', $form_state->get([
      'eca',
      'key3',
    ]));
    $this->assertEquals('Value of key4', $form_state->get([
      'eca',
      'key4',
    ]));
    $this->assertEquals('Value of key5', $form_state->get([
      'eca',
      'key5',
    ]));

    $account_switcher->switchBack();
  }

}
