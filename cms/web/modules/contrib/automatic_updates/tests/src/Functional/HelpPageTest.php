<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\Tests\package_manager\Traits\AssertPreconditionsTrait;

/**
 * @group automatic_updates
 * @internal
 */
class HelpPageTest extends AutomaticUpdatesFunctionalTestBase {

  use AssertPreconditionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'help',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the help page for Automatic Updates loads correctly.
   */
  public function testHelpPage(): void {
    $permissions = [
      'access administration pages',
      // CORE_MR_ONLY:'access help pages',
    ];
    // BEGIN: DELETE FROM CORE MERGE REQUEST
    if (array_key_exists('access help pages', $this->container->get('user.permissions')->getPermissions())) {
      $permissions[] = 'access help pages';
    }
    // END: DELETE FROM CORE MERGE REQUEST
    $user = $this->createUser($permissions);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/help/automatic_updates');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Automatic Updates');
  }

}
