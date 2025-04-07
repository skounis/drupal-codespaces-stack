<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_language\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\CustomEvent;
use Drupal\eca_language\Plugin\LanguageNegotiation\EcaLanguageNegotiation;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\StringStorageInterface;
use function current;
use function parse_url;

/**
 * Kernel tests for plugins of the eca_language module.
 *
 * @group eca
 * @group eca_language
 */
class LanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'language',
    'locale',
    'eca_language',
    'eca_base',
  ];

  /**
   * The locale string storage.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected StringStorageInterface $localeStorage;

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected ConfigurableLanguageManagerInterface $languageManager;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->localeStorage = $this->container->get('locale.storage');
    $this->languageManager = $this->container->get('language_manager');
    $this->tokenService = $this->container->get('eca.token_services');
  }

  /**
   * Tests plugins of the eca_language module.
   */
  public function testLanguage(): void {
    // Set up language negotiation.
    $config = $this->config('language.types');
    $config->set('configurable', [
      LanguageInterface::TYPE_INTERFACE,
    ]);
    $config->set('negotiation', [
      LanguageInterface::TYPE_INTERFACE => [
        'enabled' => [EcaLanguageNegotiation::METHOD_ID => -20],
      ],
    ]);
    $config->save();
    // This config does the following:
    // 1. It reacts upon language negotiation.
    // 2. Upon that, it sets german as negotiated language.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_language_negotiation',
      'label' => 'ECA language negotiation',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'language_negotiation' => [
          'plugin' => 'eca_language:negotiate',
          'label' => 'ECA language negotiation',
          'configuration' => [],
          'successors' => [
            ['id' => 'set_german_language', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_german_language' => [
          'plugin' => 'eca_set_current_langcode',
          'label' => 'Set german language',
          'configuration' => [
            'langcode' => 'de',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $action_manager->createInstance('eca_reset_language_negotiation')->execute();
    $this->assertEquals('de', $this->languageManager->getCurrentLanguage()->getId());

    $action_manager->createInstance('eca_get_current_langcode', ['token_name' => 'langcode'])->execute();
    $this->assertEquals('de', (string) $this->tokenService->replaceClear('[langcode]'));

    $action_manager->createInstance('eca_set_current_langcode', ['langcode' => 'en'])->execute();
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage()->getId());

    $action_manager->createInstance('eca_get_current_langcode', ['token_name' => 'langcode'])->execute();
    $this->assertEquals('en', (string) $this->tokenService->replaceClear('[langcode]'));
  }

  /**
   * Test the language negotiation URL.
   *
   * Test that the decorated language manager use also the
   * language set by eca_set_current_langcode for url generator.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLanguageNegotiationUrl(): void {
    // We need to reset the container or we get a false-positive.
    \Drupal::service('kernel')->rebuildContainer();
    $this->languageManager = $this->container->get('language_manager');

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes.en', 'en');
    $config->save();

    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'language_negotiation_url',
      'label' => 'Language negotiation url',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'language_negotiation_url' => [
          'plugin' => 'eca_base:eca_custom',
          'label' => 'Language negotiation url',
          'configuration' => [
            'event_id' => 'language_negotiation_url',
          ],
          'successors' => [
            ['id' => 'set_german_language', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_german_language' => [
          'plugin' => 'eca_set_current_langcode',
          'label' => 'Set german language',
          'configuration' => [
            'langcode' => 'de',
          ],
          'successors' => [
            ['id' => 'print_message', 'condition' => ''],
          ],
        ],
        'print_message' => [
          'plugin' => 'action_message_action',
          'label' => 'Print message',
          'configuration' => [
            'message' => '[site:url]',
          ],
          'successors' => [],
        ],
      ],
    ];

    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->trustData()->save();

    // Language is default.
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId());
    $this->assertEquals('/en', parse_url($this->tokenService->replaceClear('[site:url]'), PHP_URL_PATH));

    $this->container->get('event_dispatcher')->dispatch(new CustomEvent('language_negotiation_url'), BaseEvents::CUSTOM);

    // Language is unchanged by the event.
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId());
    \Drupal::service('kernel')->rebuildContainer();
    // Also after rebuild.
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId());
    $this->assertEquals('/en', parse_url($this->tokenService->replaceClear('[site:url]'), PHP_URL_PATH));

    // The model set the language to de therefore we expect that
    // also url token use this language.
    $messages = $this->container->get('messenger')->messagesByType(MessengerInterface::TYPE_STATUS);
    $this->assertEquals('/de', parse_url((string) current($messages), PHP_URL_PATH));
  }

}
