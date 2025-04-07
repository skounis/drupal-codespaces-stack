<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\package_manager\Event\PreCreateEvent;

use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use Drupal\Tests\automatic_updates\Traits\TestSetUpTrait;

/**
 * Tests errors when JavaScript is enabled.
 *
 * @group automatic_updates
 */
class UpdateErrorTest extends WebDriverTestBase {

  use TestSetUpTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'automatic_updates',
    'automatic_updates_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml');
    $user = $this->createUser([
      'administer site configuration',
      'administer software updates',
      'access administration pages',
      'access site in maintenance mode',
      'administer modules',
      'access site reports',
      'view update notifications',
    ]);
    $this->drupalLogin($user);
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
  }

  /**
   * Checks for available updates.
   *
   * Assumes that a user with appropriate permissions is logged in.
   */
  protected function checkForUpdates(): void {
    $this->drupalGet('/admin/reports/updates');
    $this->getSession()->getPage()->clickLink('Check manually');
    $this->checkForMetaRefresh();
  }

  /**
   * Mocks the current (running) version of core, as known to the Update module.
   *
   * @todo Remove this function with use of the trait from the Update module in
   *   https://drupal.org/i/3348234.
   *
   * @param string $version
   *   The version of core to mock.
   */
  protected function mockActiveCoreVersion(string $version): void {
    $this->config('update_test.settings')
      ->set('system_info.#all.version', $version)
      ->save();
  }

  /**
   * Tests that the update error page is displayed.
   */
  public function testUpdateErrorPage(): void {
    $error = ValidationResult::createError([t('Error during pre-create event')]);
    TestSubscriber::setTestResult([$error], PreCreateEvent::class);
    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/update');
    $assert_session = $this->assertSession();
    $page->pressButton('Update to 9.8.1');
    $this->assertNotNull($assert_session->waitForLink('the error page', 100000));
    $assert_session->responseContains('Error during pre-create event');
    $this->clickLink('the error page');
    $assert_session->responseContains('Error during pre-create event');
  }

}
