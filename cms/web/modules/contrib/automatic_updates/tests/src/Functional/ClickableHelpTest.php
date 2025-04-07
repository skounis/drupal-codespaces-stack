<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\Core\Url;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;

/**
 * Tests that links to online help in validation errors are clickable.
 *
 * @group automatic_updates
 * @internal
 */
class ClickableHelpTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'help',
    'package_manager_test_validation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a link to online help in a validation error is clickable.
   */
  public function testHelpLinkClickable(): void {
    $url = Url::fromRoute('help.page', ['name' => 'package_manager'])
      ->toString();

    $result = ValidationResult::createError([
      t('A problem was found! <a href=":url">Read all about it.</a>', [':url' => $url]),
    ]);
    TestSubscriber::setTestResult([$result], StatusCheckEvent::class);

    $this->drupalLogin($this->createUser([
      'administer site configuration',
      'administer software updates',
    ]));
    $this->drupalGet('admin/reports/status');
    // Status checks were run when modules were installed, and are now cached,
    // so we need to re-run the status checks to see our new result.
    // @see automatic_updates_modules_installed()
    $this->clickLink('Rerun readiness checks');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('A problem was found! Read all about it.');
    $assert_session->linkExists('Read all about it.');
    $assert_session->linkByHrefExists($url);
  }

}
