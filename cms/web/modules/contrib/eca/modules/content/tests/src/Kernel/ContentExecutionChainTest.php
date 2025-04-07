<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_form\Event\FormEvents;
use Drupal\eca_form\Event\FormProcess;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Execution chain tests using plugins of eca_content.
 *
 * @group eca
 * @group eca_content
 */
class ContentExecutionChainTest extends KernelTestBase {

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
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
    // Create the Article content type with revisioning and translation enabled.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    user_role_grant_permissions('authenticated', [
      'access content',
      'edit own article content',
    ]);
  }

  /**
   * Tests execution chains using plugins of eca_content.
   */
  public function testExecutionChain() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished node',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());

    // This config does the following:
    // 1. Loads the published node and sets its title
    // 2. Loads the unpublished node and sets its title
    // 3. Loads the published node again and sets its title yet again.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'pre_saving_node_process',
      'label' => 'ECA pre_saving node',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'event_presave' => [
          'plugin' => 'content_entity:presave',
          'label' => 'Pre-saving content',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'action_load_published', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'action_load_published' => [
          'plugin' => 'eca_token_load_entity',
          'label' => 'Load published node',
          'configuration' => [
            'token_name' => 'mynode',
            'from' => 'id',
            'entity_type' => 'node',
            'entity_id' => (string) $published_node->id(),
            'revision_id' => '',
            'properties' => '',
            'langcode' => '_interface',
            'latest_revision' => FALSE,
            'unchanged' => FALSE,
          ],
          'successors' => [
            ['id' => 'action_set_published_title', 'condition' => ''],
          ],
        ],
        'action_set_published_title' => [
          'plugin' => 'eca_set_field_value',
          'label' => 'Set title of published node',
          'configuration' => [
            'field_name' => 'title',
            'field_value' => 'Changed published TITLE for the first time!',
            'method' => 'set:clear',
            'strip_tags' => FALSE,
            'trim' => FALSE,
            'save_entity' => FALSE,
            'object' => 'mynode',
          ],
          'successors' => [
            ['id' => 'action_load_unpublished', 'condition' => ''],
          ],
        ],
        'action_load_unpublished' => [
          'plugin' => 'eca_token_load_entity',
          'label' => 'Load unpublished node',
          'configuration' => [
            'token_name' => 'mynode',
            'from' => 'id',
            'entity_type' => 'node',
            'entity_id' => (string) $unpublished_node->id(),
            'revision_id' => '',
            'properties' => '',
            'langcode' => '_interface',
            'latest_revision' => FALSE,
            'unchanged' => FALSE,
          ],
          'successors' => [
            ['id' => 'action_set_unpublished_title', 'condition' => ''],
          ],
        ],
        'action_set_unpublished_title' => [
          'plugin' => 'eca_set_field_value',
          'label' => 'Set title of unpublished node',
          'configuration' => [
            'field_name' => 'title',
            'field_value' => 'Changed TITLE of unpublished!',
            'method' => 'set:clear',
            'strip_tags' => FALSE,
            'trim' => FALSE,
            'save_entity' => FALSE,
            'object' => 'mynode',
          ],
          'successors' => [
            ['id' => 'action_load_published_again', 'condition' => ''],
          ],
        ],
        'action_load_published_again' => [
          'plugin' => 'eca_token_load_entity',
          'label' => 'Load published node again',
          'configuration' => [
            'token_name' => 'mynode',
            'from' => 'id',
            'entity_type' => 'node',
            'entity_id' => (string) $published_node->id(),
            'revision_id' => '',
            'properties' => '',
            'langcode' => '_interface',
            'latest_revision' => FALSE,
            'unchanged' => FALSE,
          ],
          'successors' => [
            ['id' => 'action_set_published_title_again', 'condition' => ''],
          ],
        ],
        'action_set_published_title_again' => [
          'plugin' => 'eca_set_field_value',
          'label' => 'Set title of published node',
          'configuration' => [
            'field_name' => 'title',
            'field_value' => 'Finally changed the published TITLE!',
            'method' => 'set:clear',
            'strip_tags' => FALSE,
            'trim' => FALSE,
            'save_entity' => FALSE,
            'object' => 'mynode',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    // Switch to privileged account.
    $account_switcher->switchTo(User::load(1));

    // Create another node and save it. That should trigger the created ECA
    // configuration which will set the node titles.
    $title = $this->randomMachineName(8);
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $node->save();

    $this->assertEquals($title, $node->label(), 'Title of node being saved must remain unchanged.');
    $this->assertEquals('Finally changed the published TITLE!', $published_node->label(), 'Title of published node must have been changed by ECA configuration.');
    $this->assertEquals('Changed TITLE of unpublished!', $unpublished_node->label(), 'Title of unpublished node must have been changed by ECA configuration.');

    // End of tests with privileged user.
    $account_switcher->switchBack();

    // The next test will execute the same configuration on a non-privileged
    // user. That user only has update access to the published node, therefore
    // the unpublished one must not be changed by ECA.
    // Disable the ECA config first to do some value resets without executing.
    $ecaConfig->disable()->trustData()->save();
    $published_node->title->value = 'Published node';
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node->title->value = 'Unpublished node';
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());
    $this->assertEquals('Published node', $published_node->label(), 'Published node title must remain unchanged.');
    $this->assertEquals('Unpublished node', $unpublished_node->label(), 'Unpublished node title must remain unchanged.');
    $ecaConfig->enable()->trustData()->save();

    // Now switch to a non-privileged account.
    $account_switcher->switchTo(User::load(2));

    // Create another node and save it. That should trigger the created ECA
    // configuration which will set the node titles.
    $title = $this->randomMachineName(8);
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    $this->assertEquals($title, $node->label(), 'Title of node being saved must remain unchanged.');
    $this->assertEquals('Changed published TITLE for the first time!', $published_node->label(), 'Title of published node must have been changed by ECA configuration only for the first time, because the process chained stopped as the unpublished entity is not accessible.');
    $this->assertEquals('Unpublished node', $unpublished_node->label(), 'Unpublished node title must remain unchanged, as it is not accessible.');

    // Reset the values once more and do another test with unprivileged user.
    $ecaConfig->disable()->trustData()->save();
    $published_node->title->value = 'Published node';
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node->title->value = 'Unpublished node';
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());
    $this->assertEquals('Published node', $published_node->label(), 'Published node title must remain unchanged.');
    $this->assertEquals('Unpublished node', $unpublished_node->label(), 'Unpublished node title must remain unchanged.');
    $ecaConfig->enable()->trustData()->save();

    // Delete the unpublished node, so that it's not available anymore.
    $unpublished_node->delete();
    $this->assertEquals('Published node', $published_node->label(), 'Published node title must remain unchanged.');

    $node->save();
    $this->assertEquals($title, $node->label(), 'Title of node being saved must remain unchanged.');
    $this->assertEquals('Changed published TITLE for the first time!', $published_node->label(), 'Title of published node must have been changed by ECA configuration only once, because subsequent actions tried to load an inaccessible node.');

    $account_switcher->switchBack();
  }

  /**
   * Tests an execution chain of multiple saving operations.
   */
  public function testEntitySaving() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'save_node_process',
      'label' => 'ECA saving node multiple times',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'event_presave' => [
          'plugin' => 'content_entity:insert',
          'label' => 'Inserted node',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'action_load_node', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'action_load_node' => [
          'plugin' => 'eca_token_load_entity',
          'label' => 'Load node',
          'configuration' => [
            'token_name' => 'mynode',
            'from' => 'current',
            'entity_type' => '',
            'entity_id' => '',
            'revision_id' => '',
            'properties' => '',
            'langcode' => '_interface',
            'latest_revision' => FALSE,
            'unchanged' => FALSE,
          ],
          'successors' => [
            ['id' => 'action_set_title', 'condition' => ''],
          ],
        ],
        'action_set_title' => [
          'plugin' => 'eca_set_field_value',
          'label' => 'Set title of node',
          'configuration' => [
            'field_name' => 'title',
            'field_value' => 'Changed title of node!',
            'method' => 'set:clear',
            'strip_tags' => FALSE,
            'trim' => FALSE,
            'save_entity' => FALSE,
            'object' => 'mynode',
          ],
          'successors' => [
            ['id' => 'action_set_new_revision_false', 'condition' => ''],
          ],
        ],
        'action_set_new_revision_false' => [
          'plugin' => 'eca_set_new_revision',
          'label' => 'Flag node to NOT set new revision',
          'configuration' => [
            'object' => 'mynode',
            'new_revision' => FALSE,
          ],
          'successors' => [
            ['id' => 'action_save_node_no_new_revision', 'condition' => ''],
          ],
        ],
        'action_save_node_no_new_revision' => [
          'plugin' => 'eca_save_entity',
          'label' => 'Save node (no new revision)',
          'configuration' => [
            'object' => 'mynode',
          ],
          'successors' => [
            ['id' => 'action_set_title_once_more', 'condition' => ''],
          ],
        ],
        'action_set_title_once_more' => [
          'plugin' => 'eca_set_field_value',
          'label' => 'Set title of node once more',
          'configuration' => [
            'field_name' => 'title',
            'field_value' => 'Changed title of node once more!',
            'method' => 'set:clear',
            'strip_tags' => FALSE,
            'trim' => FALSE,
            'save_entity' => FALSE,
            'object' => 'mynode',
          ],
          'successors' => [
            ['id' => 'action_set_new_revision_true', 'condition' => ''],
          ],
        ],
        'action_set_new_revision_true' => [
          'plugin' => 'eca_set_new_revision',
          'label' => 'Flag node to set new revision',
          'configuration' => [
            'object' => 'mynode',
            'new_revision' => TRUE,
          ],
          'successors' => [
            ['id' => 'action_save_node_with_new_revision', 'condition' => ''],
          ],
        ],
        'action_save_node_with_new_revision' => [
          'plugin' => 'eca_save_entity',
          'label' => 'Save node (new revision)',
          'configuration' => [
            'object' => 'mynode',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Original title',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();
    $node = Node::load($node->id());

    $this->assertEquals('Changed title of node once more!', $node->label());
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('type', 'article');
    $query->condition('nid', $node->id());
    $query->sort('vid');
    $query->allRevisions();
    $vids = $query->execute();
    $this->assertCount(2, $vids, "Node must have exactly two revisions.");
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision(key($vids));
    $this->assertEquals("Changed title of node!", $revision->label());

    $account_switcher->switchBack();
  }

  /**
   * Tests CRUD actions on a content entity.
   */
  public function testCrudActions(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $node->save();

    /** @var \Drupal\eca_content\Plugin\Action\NewEntity $new_action */
    $title = $this->randomMachineName();
    $new_action = $action_manager->createInstance('eca_new_entity', [
      'token_name' => 'node',
      'type' => 'node article',
      'langcode' => 'en',
      'label' => $title,
      'published' => TRUE,
      'owner' => '1',
    ]);
    /** @var \Drupal\eca_content\Plugin\Action\SaveEntity $save_action */
    $save_action = $action_manager->createInstance('eca_save_entity', [
      'object' => 'node',
    ]);
    /** @var \Drupal\eca_content\Plugin\Action\DeleteEntity $delete_action */
    $delete_action = $action_manager->createInstance('eca_delete_entity', [
      'object' => 'node',
    ]);
    $this->assertFalse($new_action->access(NULL), 'User without permissions must not have access.');
    $this->assertFalse($save_action->access($node), 'User without permissions must not have access.');
    $this->assertFalse($delete_action->access($node), 'User without permissions must not have access.');

    // Now switching to privileged user.
    $account_switcher->switchTo(User::load(1));
    $this->assertTrue($new_action->access(NULL), 'User with permission must have access.');
    $this->assertTrue($save_action->access($node), 'User with permission must have access.');
    $this->assertTrue($delete_action->access($node), 'User with permission must have access.');
    $new_action->execute();

    $node = $token_services->getTokenData('node');
    $this->assertTrue($node instanceof NodeInterface);
    $this->assertTrue($node->isNew());
    $this->assertEquals($title, $node->label());

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $this->assertEmpty($storage->loadByProperties(['title' => $title]), 'Node must not yet have been saved.');

    $save_action->execute($node);
    $node = $storage->loadByProperties(['title' => $title]);
    $node = reset($node);
    $this->assertTrue($node instanceof NodeInterface);
    $this->assertFalse($node->isNew());
    $this->assertEquals($title, $node->label());

    $title = $this->randomMachineName();
    $node->title->value = $title;
    $save_action->execute($node);
    $node = $storage->loadByProperties(['title' => $title]);
    $node = reset($node);
    $this->assertTrue($node instanceof NodeInterface);
    $this->assertFalse($node->isNew());
    $this->assertEquals($title, $node->label());

    $delete_action->execute($node);
    $this->assertEmpty($storage->loadByProperties(['title' => $title]));
  }

  /**
   * Tests execution core's "action_goto_action" using tokens.
   *
   * The core action itself doesn't support Tokens, but ECA takes care of that.
   */
  public function testRedirectAction(): void {
    // This config does the following:
    // 1. It reacts upon saving a node
    // 2. It sets a redirect using core's "action_goto_action" action.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'redirect_after_save',
      'label' => 'ECA redirect after save',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'event_insert' => [
          'plugin' => 'content_entity:insert',
          'label' => 'Insert content',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'redirect', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'redirect' => [
          'plugin' => 'action_goto_action',
          'label' => 'Do redirect',
          'configuration' => [
            'url' => '/eca-redirect/[node:nid]',
            'replace_tokens' => TRUE,
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\node\NodeInterface $article */
    $article = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => $this->randomMachineName(),
    ]);
    // Saving the new article executes above ECA configuration.
    $article->save();

    // Add a listener to the kernel response event, so that we can assert
    // for an existing redirect.
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $response = NULL;
    $event_dispatcher->addListener(KernelEvents::RESPONSE, static function ($event) use (&$response) {
      /**
       * @var \Symfony\Component\HttpKernel\Event\ResponseEvent $event
       */
      $response = $event->getResponse();
    }, -1000);

    // Ensure that there is a session on every request.
    $request = Request::createFromGlobals();
    if (!$request->hasSession()) {
      $session = new Session(new MockArraySessionStorage());
      $session->start();
      $request->setSession($session);
    }

    // Fake a response event that executes the added listener.
    $response_event = new ResponseEvent(\Drupal::service('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
    $event_dispatcher->dispatch($response_event, KernelEvents::RESPONSE);

    $this->assertInstanceOf(RedirectResponse::class, $response);
    /** @var \Symfony\Component\HttpFoundation\RedirectResponse $response */
    $this->assertEquals("/eca-redirect/{$article->id()}", mb_substr($response->getTargetUrl(), -15));
  }

  /**
   * Tests an execution chain using entity form components.
   */
  public function testEntityFormComponents(): void {
    // Install the base module for executing "eca_token_set_value".
    // Also install the form module for reacting upon form processing.
    /** @var \Drupal\Core\Extension\ModuleInstaller $module_installer */
    $module_installer = \Drupal::service('module_installer');
    $module_installer->install(['eca_base', 'eca_form', 'options']);

    // Create a single-value list field that makes use of FieldOptions.
    FieldStorageConfig::create([
      'field_name' => 'field_selection',
      'type' => 'list_string',
      'entity_type' => 'node',
      'settings' => [
        'allowed_values' => [],
        'allowed_values_function' => 'Drupal\eca_content\FieldOptions::eventBasedValues',
      ],
      'module' => 'options',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_selection',
      'label' => 'Selection',
      'entity_type' => 'node',
      'bundle' => 'article',
      'default_value' => [],
      'field_type' => 'list_string',
    ])->save();
    // Create a single-value entity reference field.
    FieldStorageConfig::create([
      'field_name' => 'field_content',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'node',
      ],
      'module' => 'core',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_content',
      'label' => 'Content',
      'entity_type' => 'node',
      'bundle' => 'article',
      'default_value' => [],
      'settings' => [
        'handler' => 'eca',
        'handler_settings' => [
          'field_name' => 'field_content',
        ],
      ],
      'field_type' => 'entity_reference',
    ])->save();

    EntityFormDisplay::collectRenderDisplay(Node::create(['type' => 'article']), 'default')->save();
    $form_display = EntityFormDisplay::load('node.article.default');
    $form_display->setComponent('field_selection', ['type' => 'options_select']);
    $form_display->setComponent('field_content', ['type' => 'options_select']);
    $form_display->save();

    // Create a new custom form display that is basically just a copy of the
    // default form display.
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'custom',
      'status' => TRUE,
      'content' => $form_display->get('content'),
      'hidden' => $form_display->get('hidden'),
      'dependencies' => $form_display->get('dependencies'),
    ])->trustData()->setSyncing(TRUE)->save();

    // Create two article nodes for referencing.
    $nids = [];
    $article = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => $this->randomMachineName(),
    ]);
    $article->save();
    $nids[] = (int) $article->id();
    $article = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => $this->randomMachineName(),
    ]);
    $article->save();
    $nids[] = (int) $article->id();

    // This config does the following:
    // 1. Reacts upon options selection and entity reference selection
    //    and sets custom allowed values.
    // 2. Reacts upon preparing a form to set a different form display.
    // 3. Reacts upon form processing and checks whether the form display
    //    had been changed accordingly. It sets a custom form state property
    //    for asserting that this condition was met.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'entity_form_components',
      'label' => 'ECA entity form components',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'options_selection' => [
          'plugin' => 'content_entity:options_selection',
          'label' => 'Options selection',
          'configuration' => [
            'type' => 'node article',
            'field_name' => 'field_selection',
            'token_name' => 'options',
          ],
          'successors' => [
            ['id' => 'set_options', 'condition' => ''],
          ],
        ],
        'content_selection' => [
          'plugin' => 'content_entity:reference_selection',
          'label' => 'Content reference selection',
          'configuration' => [
            'type' => 'node article',
            'field_name' => 'field_content',
            'token_name' => 'content',
          ],
          'successors' => [
            ['id' => 'set_references', 'condition' => ''],
          ],
        ],
        'prepare_form' => [
          'plugin' => 'content_entity:prepareform',
          'label' => 'Prepare form',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'set_custom_display', 'condition' => ''],
          ],
        ],
        'process_form' => [
          'plugin' => 'form:form_process',
          'label' => 'Process form',
          'configuration' => [
            'form_id' => '',
            'entity_type_id' => 'node',
            'bundle' => 'article',
            'operation' => '',
          ],
          'successors' => [
            ['id' => 'set_form_state_property', 'condition' => 'display_mode'],
          ],
        ],
      ],
      'conditions' => [
        'display_mode' => [
          'plugin' => 'eca_content_form_display_mode',
          'configuration' => [
            'display_mode' => 'custom',
          ],
        ],
      ],
      'gateways' => [],
      'actions' => [
        'set_options' => [
          'plugin' => 'eca_token_set_value',
          'label' => 'Set allowed options',
          'configuration' => [
            'token_name' => 'options',
            'token_value' => <<<YAML
custom1: Custom Value One
custom2: Custom Value Two
YAML,
            'use_yaml' => TRUE,
          ],
          'successors' => [],
        ],
        'set_references' => [
          'plugin' => 'eca_token_set_value',
          'label' => 'Set allowed content',
          'configuration' => [
            'token_name' => 'content',
            'token_value' => <<<YAML
0: {$nids[0]}
1: {$nids[1]}
YAML,
            'use_yaml' => TRUE,
          ],
          'successors' => [],
        ],
        'set_custom_display' => [
          'plugin' => 'eca_content_set_form_display',
          'label' => 'Set custom display',
          'configuration' => [
            'display_mode' => 'custom',
          ],
          'successors' => [],
        ],
        'set_form_state_property' => [
          'plugin' => 'eca_form_state_set_property_value',
          'label' => 'Set custom form state property',
          'configuration' => [
            'property_name' => 'customprop',
            'property_value' => 'Success!',
            'use_yaml' => FALSE,
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    $article = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => $this->randomMachineName(),
    ]);
    // Saving the new article executes above ECA configuration.
    $article->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $form_builder = \Drupal::formBuilder();

    $form = NULL;
    $form_display_mode = NULL;
    $event_dispatcher->addListener(FormEvents::PROCESS, function (FormProcess $event) use (&$form, &$form_display_mode) {
      $form_object = $event->getFormState()->getFormObject();
      if ($form_object instanceof ContentEntityFormInterface) {
        $form_display_mode = $form_object->getFormDisplay($event->getFormState())->getMode();
      }
      $form = $event->getForm();
    });

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'default');
    $form_object->setEntity($article);
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);

    $this->assertTrue(isset($form['field_selection']['widget']['#options']));
    $this->assertSame([
      '_none' => '- None -',
      'custom1' => 'Custom Value One',
      'custom2' => 'Custom Value Two',
    ], $form['field_selection']['widget']['#options']);
    $this->assertTrue(isset($form['field_content']['widget']['#options']));
    $this->assertSame(array_merge(['_none'], $nids), array_keys($form['field_content']['widget']['#options']));
    $this->assertEquals('custom', $form_display_mode);
    $this->assertEquals('Success!', $form_state->get(['eca', 'customprop']));
  }

}
