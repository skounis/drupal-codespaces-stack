<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\FunctionalJavascript;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\automatic_updates\Traits\TestSetUpTrait;

/**
 * @group automatic_updates
 */
class UpdateSettingsFormTest extends WebDriverTestBase {

  use TestSetUpTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Tests Automatic Updates' alterations to the update settings form.
   */
  public function testSettingsForm(): void {
    $account = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/reports/updates/settings');

    // The default values should be reflected.
    $assert_session = $this->assertSession();
    $assert_session->fieldValueEquals('unattended_method', 'web');
    $assert_session->fieldValueEquals('unattended_level', CronUpdateRunner::DISABLED);
    // Since unattended updates are disabled, the method radio buttons should be
    // hidden.
    $this->assertFalse($assert_session->fieldExists('unattended_method')->isVisible());

    // Enabling unattended updates should reveal the method radio buttons.
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('unattended_level', CronUpdateRunner::SECURITY);
    $this->assertNotEmpty($assert_session->waitForElementVisible('named', ['field', 'unattended_method']));
    $assert_session->elementAttributeContains('named', ['link', 'ensure cron is set up correctly'], 'href', 'http://drupal.org/docs/user_guide/en/security-cron.html');
    // Change the method, to ensure it is properly saved in config.
    $page->selectFieldOption('unattended_method', 'console');

    // Ensure the changes are reflected in config.
    $page->pressButton('Save configuration');
    $config = $this->config('automatic_updates.settings');
    $this->assertSame(CronUpdateRunner::SECURITY, $config->get('unattended.level'));
    $this->assertSame('console', $config->get('unattended.method'));
    // Our saved changes should be reflected in the form too.
    $assert_session->fieldValueEquals('unattended_level', CronUpdateRunner::SECURITY);
    $assert_session->fieldValueEquals('unattended_method', 'console');
  }

}
