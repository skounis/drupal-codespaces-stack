<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Core\Url;
use Drupal\scheduler\Form\SchedulerCronForm;

/**
 * Tests the Scheduler lightweight cron urls and admin form.
 *
 * @group scheduler
 */
class SchedulerLightweightCronTest extends SchedulerBrowserTestBase {

  /**
   * Test scheduler lightweight cron runs.
   */
  public function testLightweightCronRun() {
    // Run scheduler lightweight cron anonymously without any cron key.
    // The response with no key should be "404 Not Found".
    $this->drupalGet('scheduler/cron');
    $this->assertSession()->statusCodeEquals(404);

    // Run scheduler lightweight cron anonymously with a random cron key.
    // The response for an incorrect key should be "403 Access Denied".
    $key = substr(md5(rand()), 0, 20);
    $this->drupalGet('scheduler/cron/' . $key);
    $this->assertSession()->statusCodeEquals(403);

    // Run scheduler lightweight cron anonymously with the valid cron key which
    // is defined during install. It should run OK but no content will be
    // produced so the response should be "204 No Content".
    $key = $this->container->get('state')->get(SchedulerCronForm::CRON_ACCESS_KEY, '');
    $this->drupalGet('scheduler/cron/' . $key);
    $this->assertSession()->statusCodeEquals(204);
  }

  /**
   * Test the lightweight cron settings form.
   */
  public function testLightweightCronSettingsForm() {
    // Log in.
    $this->drupalLogin($this->adminUser);

    $cronForm = Url::fromRoute('scheduler.cron_form');

    // Check that the cron key has an initial value, created during install.
    $this->drupalGet($cronForm);
    $key_xpath = $this->xpath('//input[@id="edit-lightweight-access-key"]/@value');
    $key = $key_xpath[0]->getText();
    $this->assertNotEmpty($key, 'The default lightweight cron key field should not be empty');
    $this->assertEquals(20, strlen($key), 'The default lightweight cron key string length should be 20');

    // Check that a new random key can be generated.
    $this->drupalGet($cronForm);
    $this->submitForm([], 'Generate new random key');
    $new_key_xpath = $this->xpath('//input[@id="edit-lightweight-access-key"]/@value');
    $new_key = $new_key_xpath[0]->getText();
    $this->assertNotEmpty($new_key, 'The lightweight cron key field should not be empty after generating a new key');
    $this->assertEquals(20, strlen($new_key), 'The new lightweight cron key string length should be 20');
    $this->assertNotEquals($new_key, $key, 'The new lightweight cron key should be different from the previous key.');

    // Check that the 'run lightweight cron' button works.
    $this->drupalGet($cronForm);
    $this->submitForm([], "Run Scheduler's lightweight cron now");
    $this->assertSession()->pageTextContains('Lightweight cron run completed.');

    // Check that the form cannot be saved if the cron key is blank.
    $this->drupalGet($cronForm);
    $this->submitForm(['lightweight_access_key' => ''], 'Save configuration');
    $this->assertSession()->pageTextContains('Lightweight cron access key field is required.');
    $this->assertSession()->pageTextNotContains('The configuration options have been saved.');
  }

}
