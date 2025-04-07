<?php

namespace Drupal\Tests\simple_sitemap\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Provides the base class for web tests for Simple sitemap.
 */
abstract class SimplesitemapTestBase extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'simple_sitemap',
    'node',
    'content_translation',
  ];

  /**
   * Simple sitemap generator.
   *
   * @var \Drupal\simple_sitemap\Manager\Generator
   */
  protected $generator;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * A user with all the permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $privilegedUser;

  /**
   * A node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * A node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node2;

  /**
   * The default sitemap URL.
   *
   * @var string
   */
  protected $defaultSitemapUrl = 'sitemap.xml';

  /**
   * Use the stable9 theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stable9';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->generator = $this->container->get('simple_sitemap.generator');
    $this->database = $this->container->get('database');

    $this->drupalCreateContentType(['type' => 'page']);
    $this->node = $this->createNode(['title' => 'Node', 'type' => 'page']);
    $this->node2 = $this->createNode(['title' => 'Node2', 'type' => 'page']);

    // Create a user with all the permissions.
    $permissions = array_keys($this->container->get('user.permissions')->getPermissions());
    $this->privilegedUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Helper function to replace assertUniqueText.
   *
   * Also adapt the legacy trait method because it can't be applied to Non-HTML
   * pages.
   *
   * @param string $text
   *   The text to look for.
   *
   * @See \Drupal\FunctionalTests\AssertLegacyTrait::assertUniqueText().
   */
  protected function assertUniqueTextWorkaround($text) {
    $page_content = $this->getSession()->getPage()->getContent();
    $nr_found = substr_count($page_content, $text);
    $this->assertSame(1, $nr_found);
  }

  /**
   * Helper function to replace assertNoUniqueText.
   *
   * Also adapt the legacy trait method because it can't be applied to Non-HTML
   * pages.
   *
   * @param string $text
   *   The text to look for.
   *
   * @See \Drupal\FunctionalTests\AssertLegacyTrait::assertNoUniqueText().
   */
  protected function assertNoUniqueTextWorkaround($text) {
    $page_text = $this->getSession()->getPage()->getContent();
    $nr_found = substr_count($page_text, $text);
    $this->assertGreaterThan(1, $nr_found);
  }

  /**
   * Helper function to create languages.
   *
   * @param array|string $langcodes
   *   An array of language codes or the single language code.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function addLanguages($langcodes = 'de') {
    foreach ((array) $langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)
        ->save();
    }
  }

}
