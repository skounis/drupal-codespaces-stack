<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Drupal\Core\Language\LanguageInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

// cspell:ignore soorch foor moodools

/**
 * Tests Svelte app translations.
 *
 * @group project_browser
 */
final class TranslatedSvelteAppTest extends WebDriverTestBase {

  use ProjectBrowserUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser',
    'project_browser_test',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Confirms the Svelte app is translatable.
   *
   * 90% of this is code borrowed from
   * \Drupal\Tests\locale\Functional\LocaleContentTest.
   */
  public function testTranslation(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'translate interface',
      'administer modules',
    ]);

    // Add custom language.
    $this->drupalLogin($admin_user);
    // Code for the language.
    $langcode = 'es';
    // The English name for the language.
    $name = $this->randomMachineName(16);
    // The domain prefix.
    $prefix = $langcode;
    $edit = [
      'predefined_langcode' => 'custom',
      'langcode' => $langcode,
      'label' => $name,
      'direction' => LanguageInterface::DIRECTION_LTR,
    ];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add custom language');

    // Set path prefix.
    $edit = ["prefix[$langcode]" => $prefix];
    $this->drupalGet('admin/config/regional/language/detection/url');
    $this->submitForm($edit, 'Save configuration');

    $translate_to = 'Soorch Foor Moodools';

    $this->drupalGet('admin/modules/browse/drupalorg_jsonapi');
    $this->svelteInitHelper('text', 'Search');
    $this->assertFalse($this->assertSession()->waitForText($translate_to));

    // This forces locale JS string sources to be imported.
    $this->drupalGet($prefix . '/admin/config/regional/translate');

    // Translate a string in locale.admin.js to our new language.
    $strings = \Drupal::service('locale.storage')
      ->getStrings([
        'source' => 'Search',
      ]);

    $string = $strings[0];

    $this->submitForm(['string' => 'Search'], 'Filter');
    $edit = ['strings[' . $string->lid . '][translations][0]' => $translate_to];
    $this->submitForm($edit, 'Save translations');
    $this->drupalGet("/$prefix/admin/modules/browse/drupalorg_jsonapi");
    $this->svelteInitHelper('text', $translate_to);
  }

}
