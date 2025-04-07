<?php

namespace Drupal\Tests\klaro\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Check if local klaro logic can replace html tag src attributes.
 */
class TemplateProcessFinalHtmlTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'klaro', 'klaro__testing',
  ];
  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $assert_session = $this->assertSession();
    $this->drupalLogin(
        $this->drupalCreateUser(['administer klaro'])
    );
    // Check if user interface can be reached.
    $this->drupalGet('admin/config/user-interface/klaro');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Klaro! settings');

    $this->submitForm([
      'final_html' => TRUE,
    ], 'Save configuration');
    $assert_session->pageTextNotContains('Error Message', 'Could not update config.');
    $assert_session->checkboxChecked('final_html');

    $this->drupalGet('admin/config/user-interface/klaro/services');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Example');

    $this->drupalLogout();
  }

  /**
   * Check if iframes / scripts will be replaced by "processFinalHtml".
   *
   * @var void
   */
  public function testTemplate() {
    $assert_session = $this->assertSession();
    $adminUser = $this->drupalCreateUser(['access content', 'use klaro']);

    $this->drupalLogin($adminUser);

    $this->drupalGet('klaro--testing/example-iframe');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.org/iframe")]');

    $this->drupalGet('klaro--testing/example-scripts');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.org/js/script.js")]');

    // Test unknown?
  }

}
