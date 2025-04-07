<?php

namespace Drupal\Tests\friendlycaptcha\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test basic functionality of friendlycaptcha module.
 *
 * @group friendlycaptcha
 *
 * @dependencies captcha
 */
class FriendlyCaptchaBasicTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * A normal user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $normalUser;

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['captcha', 'friendlycaptcha'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::moduleHandler()->loadInclude('captcha', 'inc');

    // Create a normal user.
    $permissions = [
      'access content',
    ];
    $this->normalUser = $this->drupalCreateUser($permissions);

    // Create an admin user.
    $permissions = [
      'access content',
      'administer CAPTCHA settings',
      'skip CAPTCHA',
      'administer permissions',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Helper function to generate a random Site Key.
   *
   * @return string
   *   Generated 16 char random site key.
   */
  protected function generateSiteKey() {
    $site_key = $this->randomMachineName(16);
    return $site_key;
  }

  /**
   * Helper function to generate a random API Key.
   *
   * @return string
   *   Generated 60 char random api key.
   */
  protected function generateApiKey() {
    $api_key = $this->randomMachineName(60);
    return $api_key;
  }

  /**
   * Test access to the administration page.
   */
  public function testFriendlycaptchaSettingsAdminAccess() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $this->assertSession()->pageTextNotContains($this->t('Access denied'));
    $this->drupalLogout();
  }

  /**
   * Test the Friendlycaptcha settings form.
   */
  public function testFriendlycaptchaAdminSettingsForm() {
    $this->drupalLogin($this->adminUser);

    $site_key = $this->generateSiteKey();
    $api_key = $this->generateApiKey();
    $endpoint = 'global';

    // Check form validation.
    $edit['friendlycaptcha_site_key'] = '';
    $edit['friendlycaptcha_api_key'] = '';
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->responseContains($this->t('Site key field is required.'));
    $this->assertSession()->responseContains($this->t('API key field is required.'));

    // Save form with valid values.
    $edit['friendlycaptcha_site_key'] = $site_key;
    $edit['friendlycaptcha_api_key'] = $api_key;
    $edit['friendlycaptcha_api_endpoint'] = $endpoint;
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains($this->t('The configuration options have been saved.'));

    $this->assertSession()->responseNotContains($this->t('Site key field is required.'));
    $this->assertSession()->responseNotContains($this->t('Secret key field is required.'));
    $this->assertSession()->responseNotContains($this->t('The tabindex must be an integer.'));

    $this->drupalLogout();
  }

  /**
   * Test the Friendlycaptcha settings form access.
   */
  public function testFriendlycaptchaAdminSettingsFormAccess() {
    $session = $this->assertSession();
    // Login as the admin user:
    $this->drupalLogin($this->adminUser);
    // The admin user should have access to the friendly captcha place.
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $session->statusCodeEquals(200);
    $session->pageTextContains('Friendly Captcha');

    // An anonymous user shouldn't have access:
    $this->drupalLogout();
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $session->statusCodeEquals(403);

    // Login with a user without the right permisson:
    $this->drupalLogin($this->normalUser);
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $session->statusCodeEquals(403);

    // Login with a user with the correct permissions:
    $captchaSettingsUser = $this->drupalCreateUser(['administer CAPTCHA settings']);
    $this->drupalLogin($captchaSettingsUser);
    $this->drupalGet('admin/config/people/captcha/friendlycaptcha');
    $session->statusCodeEquals(200);
    $session->pageTextContains('Friendly Captcha');
  }

  /**
   * Testing the protection of the user login form.
   */
  public function testFriendlycaptchaOnLoginForm() {
    $site_key = $this->generateSiteKey();
    $api_key = $this->generateApiKey();

    $friendlyCaptchaHtml = '<div class="frc-captcha" data-sitekey="' . $site_key . '" data-lang="en">';
    $friendlyCaptchaNoScriptHtml = '<noscript>' . t('You need Javascript for CAPTCHA verification to submit this form.') . '</noscript>';

    // Test if login works.
    $this->drupalLogin($this->normalUser);
    $this->drupalLogout();

    $this->drupalGet('user/login');
    $this->assertSession()->responseNotContains($friendlyCaptchaHtml);
    $this->assertSession()->responseNotContains($friendlyCaptchaNoScriptHtml);

    // Enable 'captcha/Math' CAPTCHA on login form.
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');

    $this->drupalGet('user/login');
    $this->assertSession()->responseNotContains($friendlyCaptchaHtml);
    $this->assertSession()->responseNotContains($friendlyCaptchaNoScriptHtml);

    // Enable 'friendlycaptcha/friendlycaptcha' on login form.
    captcha_set_form_id_setting('user_login_form', 'friendlycaptcha/friendlycaptcha');
    $result = captcha_get_form_id_setting('user_login_form');
    $this->assertNotNull($result, 'A configuration has been found for CAPTCHA point: user_login_form');
    $this->assertEquals($result->getCaptchaType(), 'friendlycaptcha/friendlycaptcha', 'Friendlycaptcha type has been configured for CAPTCHA point: user_login_form');

    // Check if a Math CAPTCHA is still shown on the login form. The site key
    // and security key have not yet configured for Friendlycaptcha.
    // The module needs to fall back to math captcha.
    $this->drupalGet('user/login');
    $this->assertSession()->responseContains($this->t('Math question'));

    // Configure site key and security key to show Friendlycaptcha
    // and no fall back.
    $this->config('friendlycaptcha.settings')
      ->set('site_key', $site_key)
      ->set('api_key', $api_key)
      ->save();

    // Check if there is a Friendlycaptcha on the login form.
    $this->drupalGet('user/login');
    $this->assertSession()->responseContains($friendlyCaptchaHtml);
    $this->assertSession()->responseContains('friendly-challenge/widget.min.js');
    $this->assertSession()->responseContains($friendlyCaptchaNoScriptHtml);

    // @todo Check that the widget is not only present, but also loaded!
    // Try to log in, which should fail.
    $edit['name'] = $this->normalUser->getAccountName();
    $edit['pass'] = $this->normalUser->getPassword();
    $this->assertSession()->responseContains('captcha_response');
    $this->assertSession()
      ->hiddenFieldExists('captcha_response')
      ->setValue('?');
    $this->drupalGet('user/login');

    $this->submitForm($edit, 'Log in');
    // Check for error message.
    $this->assertSession()->pageTextContains($this->t('The answer you entered for the CAPTCHA was not correct.'));

    // And make sure that user is not logged in: check for name and password
    // fields on "?q=user".
    $this->drupalGet('user/login');
    $this->assertSession()->fieldExists('name');
    $this->assertSession()->fieldExists('pass');
  }

}
