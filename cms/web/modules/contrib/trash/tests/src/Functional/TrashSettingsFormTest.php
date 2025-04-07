<?php

namespace Drupal\Tests\trash\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the trash settings form.
 *
 * @group trash
 */
class TrashSettingsFormTest extends BrowserTestBase {

  /**
   * A user with permission to configure the trash module.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'media', 'node', 'trash'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Basic page node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
    }

    $this->adminUser = $this->drupalCreateUser([
      'administer trash',
    ]);
    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'page_tabs_block']);
    $this->drupalPlaceBlock('local_actions_block', ['id' => 'page_actions_block']);
  }

  /**
   * Test storing trash settings.
   */
  public function testStoringSettings() {
    // Login as the privileged user.
    $this->drupalLogin($this->adminUser);

    // Load the settings form.
    $this->drupalGet('admin/config/content/trash');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('Trash Settings | Drupal');

    $this->assertSession()->checkboxChecked('enabled_entity_types[node][enabled]');
    $this->assertSession()->checkboxNotChecked('enabled_entity_types[media][enabled]');

    $this->assertSession()->checkboxNotChecked('auto_purge[enabled]');
    $this->assertSession()->fieldValueEquals('auto_purge[after]', '30 days');

    $edit = [
      'enabled_entity_types[node][enabled]' => FALSE,
      'enabled_entity_types[media][enabled]' => TRUE,
      'auto_purge[enabled]' => TRUE,
      'auto_purge[after]' => '45 days',
    ];
    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->checkboxNotChecked('enabled_entity_types[node][enabled]');
    $this->assertSession()->checkboxChecked('enabled_entity_types[media][enabled]');
    $this->assertSession()->checkboxChecked('auto_purge[enabled]');
    $this->assertSession()->fieldValueEquals('auto_purge[after]', '45 days');
  }

  /**
   * Test storing trash settings.
   */
  public function testFailingValidatingSettings() {
    // Login as the privileged user.
    $this->drupalLogin($this->adminUser);

    // Load the settings form.
    $this->drupalGet('admin/config/content/trash');

    $edit = [
      'auto_purge[after]' => '1 month and 3 hours',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusMessageContains("The time period '1 month and 3 hours' is not valid.", 'error');

    $edit = [
      'auto_purge[after]' => 'Last saturday',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusMessageContains("The time period 'Last saturday' is not valid.", 'error');

    $edit = [
      'auto_purge[after]' => 'Next saturday',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusMessageContains("The time period 'Next saturday' is not valid.", 'error');

    $edit = [
      'auto_purge[after]' => '3 days, 1 hour',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusMessageContains('The configuration options have been saved.', 'status');
  }

}
