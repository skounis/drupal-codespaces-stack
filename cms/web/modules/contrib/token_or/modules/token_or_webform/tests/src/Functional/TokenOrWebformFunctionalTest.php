<?php

namespace Drupal\Tests\token_or_webform\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Test that the module works.
 *
 * @group token_or_webform
 */
class TokenOrWebformFunctionalTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'token_or',
    'webform',
    'token_or_webform_test',
    'token_or_webform',
  ];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $anon_role = Role::load(Role::ANONYMOUS_ID);
    $this->grantPermissions($anon_role, ['access content']);
  }

  /**
   * Tests token [current-page:query:cid|"foobar"] when get param is present.
   */
  public function testCurrentPageGetParamPresent() {
    $params = [
      'cid' => 'param_cid',
    ];
    $this->drupalGet('webform/test', ['query' => $params]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//*[@id="edit-current-page-query"][@value="Lorem lorem param_cid"]');
  }

  /**
   * Tests token [current-page:query:cid|"foobar"] when get param is missing.
   */
  public function testCurrentPageGetParamMissing() {
    // If not exists first param, use fallback.
    $this->drupalGet('webform/test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//*[@id="edit-current-page-query"][@value="Lorem lorem foobar"]');
  }

  /**
   * Tests token [current-page:query:cid:clear|"foobar"]...
   *
   * When get param is missing.
   */
  public function testCurrentPageGetParamMissingClear() {
    // If not exists first param, use fallback.
    $this->drupalGet('webform/test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//*[@id="edit-current-page-query-clear"][@value="Lorem lorem foobar"]');
  }

  /**
   * Tests token [current-user:display-name] still returns nothing.
   */
  public function testAnonymousCurrentUser() {
    $this->drupalGet('webform/test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//*[@id="edit-current-user-display-name"][@value="Lorem lorem "]');
  }

  /**
   * Tests token [current-user:display-name|"Nothing"] returns its fallback.
   */
  public function testAnonymousCurrentUserFallback() {
    $this->drupalGet('webform/test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//*[@id="edit-current-user-display-name-fallback"][@value="Lorem lorem Nothing"]');
  }

}
