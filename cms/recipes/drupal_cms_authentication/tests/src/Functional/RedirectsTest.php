<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_authentication\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * @group drupal_cms_authentication
 */
class RedirectsTest extends BrowserTestBase {

  use AssertMailTrait;
  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testRedirects(): void {
    $dir = realpath(__DIR__ . '/../../..');
    $this->applyRecipe($dir);
    // Apply the recipe again to prove that it's idempotent.
    $this->applyRecipe($dir);

    $assert_session = $this->assertSession();

    // A 403 should redirect to the login page, forwarding to the original
    // destination.
    $this->drupalGet('/admin');
    $assert_session->statusCodeEquals(403);
    $assert_session->hiddenFieldValueEquals('form_id', 'user_login_form');
    $assert_session->buttonExists('Log in');

    // We should be able to log in with our email address. Upon logging out, we
    // should be redirected back to the login page.
    $this->drupalGet('/user/login');
    $this->submitForm([
      'name' => $this->rootUser->getEmail(),
      'pass' => $this->rootUser->passRaw,
    ], 'Log in');
    $assert_session->addressEquals('/user/' . $this->rootUser->id());
    $this->drupalLogout();
    $assert_session->addressEquals('/user/login');

    // We shouldn't get any special redirection if we're resetting our password.
    $this->drupalGet('/user/password');
    $this->submitForm([
      'name' => $this->rootUser->getAccountName(),
    ], 'Submit');
    $mail = $this->getMails();
    $this->assertNotEmpty($mail);
    $this->assertSame('user_password_reset', $mail[0]['id']);
    $matches = [];
    preg_match('/^http.+/m', $mail[0]['body'], $matches);
    $this->drupalGet($matches[0]);
    $assert_session->addressMatches('|/user/reset/|');
    $uid = $this->rootUser->id();
    $assert_session->buttonExists('Log in')->press();
    // But once we log in, we are redirected to the user's profile form, to
    // change the password.
    $assert_session->addressEquals("/user/$uid/edit");
  }

}
