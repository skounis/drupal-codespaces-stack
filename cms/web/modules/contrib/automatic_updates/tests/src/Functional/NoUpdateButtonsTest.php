<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

/**
 * @covers \Drupal\automatic_updates\Form\UpdaterForm
 * @group automatic_updates
 * @internal
 */
class NoUpdateButtonsTest extends UpdaterFormTestBase {

  /**
   * Data provider for URLs to the update form.
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerUpdateFormReferringUrl(): array {
    return [
      'Modules page' => ['/admin/modules/update'],
      'Reports page' => ['/admin/reports/updates/update'],
    ];
  }

  /**
   * Tests that the form doesn't display any buttons if Drupal is up-to-date.
   *
   * @todo Mark this test as skipped if the web server is PHP's built-in, single
   *   threaded server in https://drupal.org/i/3348251.
   *
   * @param string $update_form_url
   *   The URL of the update form to visit.
   *
   * @dataProvider providerUpdateFormReferringUrl
   */
  public function testFormNotDisplayedIfAlreadyCurrent(string $update_form_url): void {
    $this->mockActiveCoreVersion('9.8.1');
    $this->checkForUpdates();

    $this->drupalGet($update_form_url);

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('No update available');
    $this->assertNoUpdateButtons();
  }

  /**
   * Tests that updating to a different minor version isn't supported.
   *
   * @param string $update_form_url
   *   The URL of the update form to visit.
   *
   * @dataProvider providerUpdateFormReferringUrl
   */
  public function testMinorVersionUpdateNotSupported(string $update_form_url): void {
    $this->mockActiveCoreVersion('9.7.1');
    $this->checkForUpdates();

    $this->drupalGet($update_form_url);

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Updates were found, but they must be performed manually. See the list of available updates for more information.');
    $this->clickLink('the list of available updates');
    $assert_session->elementExists('css', 'table.update');
    $this->assertNoUpdateButtons();
  }

}
