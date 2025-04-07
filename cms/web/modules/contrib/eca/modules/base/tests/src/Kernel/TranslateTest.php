<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\locale\StringStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUser;

/**
 * Kernel tests for the "eca_translate" action plugin.
 *
 * @group eca
 * @group eca_base
 */
class TranslateTest extends KernelTestBase {

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
    'eca_base',
    'language',
    'locale',
    'content_translation',
  ];

  /**
   * The locale string storage.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected StringStorageInterface $localeStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('locale', [
      'locales_location',
      'locales_source',
      'locales_target',
    ]);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'preferred_langcode' => 'de',
    ])->save();

    ConfigurableLanguage::create(['id' => 'de'])->save();
    // Set up language negotiation.
    $config = $this->config('language.types');
    $config->set('configurable', [
      LanguageInterface::TYPE_INTERFACE,
      LanguageInterface::TYPE_CONTENT,
    ]);
    $config->set('negotiation', [
      LanguageInterface::TYPE_INTERFACE => [
        'enabled' => [LanguageNegotiationUser::METHOD_ID => 0],
      ],
      LanguageInterface::TYPE_CONTENT => [
        'enabled' => [LanguageNegotiationUrl::METHOD_ID => 0],
      ],
    ]);
    $config->save();
    $this->localeStorage = $this->container->get('locale.storage');
  }

  /**
   * Tests the "eca_translate" action plugin.
   */
  public function testTranslate() {
    // Create the Article content type with revisioning and translation enabled.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    ContentLanguageSettings::create([
      'id' => 'node.article',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'article',
      'default_langcode' => LanguageInterface::LANGCODE_DEFAULT,
      'language_alterable' => TRUE,
    ])->save();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'title' => 'Hello!',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $node->save();

    $token_services->addTokenData('node', $node);

    $node->addTranslation('de', [
      'type' => 'article',
      'title' => 'Hallo!',
      'langcode' => 'de',
      'uid' => 1,
      'status' => 0,
    ])->save();

    $account_switcher->switchTo(User::load(1));

    /** @var \Drupal\eca_base\Plugin\Action\Translate $action */
    $action = $action_manager->createInstance('eca_translate', [
      'token_name' => 'german',
      'value' => '[node]',
      'use_yaml' => FALSE,
      'target_langcode' => 'de',
    ]);
    $action->execute();

    $this->assertTrue($token_services->hasTokenData('german'));
    $this->assertTrue($token_services->getTokenData('german') instanceof NodeInterface);
    $this->assertEquals('Hallo!', $token_services->getTokenData('german')->label());

    // Create source string.
    $source = $this->localeStorage->createString(
      [
        'source' => "An english text",
      ]
    );
    $source->save();
    $this->localeStorage->createTranslation([
      'lid' => $source->lid,
      'language' => 'de',
      'translation' => "Ein deutschsprachiger Text",
    ])->save();

    $token_services->addTokenData('english_text', "An english text");

    /** @var \Drupal\eca_base\Plugin\Action\Translate $action */
    $action = $action_manager->createInstance('eca_translate', [
      'token_name' => 'german_text',
      'value' => '[english_text]',
      'use_yaml' => FALSE,
      'target_langcode' => 'de',
    ]);
    $action->execute();

    $this->assertEquals("An english text", $token_services->replace('[english_text]'));
    $this->assertEquals("Ein deutschsprachiger Text", $token_services->replace('[german_text]'));

    // Test again with a static string.
    $token_services->addTokenData('german_text', NULL);
    /** @var \Drupal\eca_base\Plugin\Action\Translate $action */
    $action = $action_manager->createInstance('eca_translate', [
      'token_name' => 'german_text',
      'value' => 'An english text',
      'use_yaml' => FALSE,
      'target_langcode' => 'de',
    ]);
    $action->execute();

    $this->assertEquals("Ein deutschsprachiger Text", $token_services->replace('[german_text]'));

    $token_services->addTokenData('german_text', NULL);
    $this->assertEquals("[german_text]", $token_services->replace('[german_text]'));
    /** @var \Drupal\eca_base\Plugin\Action\Translate $action */
    $action = $action_manager->createInstance('eca_translate', [
      'token_name' => 'german_text',
      'value' => 'An english text',
      'use_yaml' => FALSE,
      'target_langcode' => '_preferred',
    ]);
    $action->execute();

    $this->assertEquals("Ein deutschsprachiger Text", $token_services->replace('[german_text]'));

    $account_switcher->switchBack();
  }

}
