<?php

namespace Drupal\Tests\sam\FunctionalJavascript;

use Drupal\Core\Url;

/**
 * Tests the text configurability of Simple Add More.
 *
 * @group sam
 */
class ConfigurationFunctionalTest extends SamFunctionalJavascriptTestBase {

  /**
   * Tests the text configurability of Simple Add More.
   */
  public function testFormSimplification() {
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    // Log in as admin.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'bypass node access',
      'administer sam',
    ]);
    $this->drupalLogin($this->adminUser);

    // Access the node add page.
    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    // Checks if the default remaining items help text (plural) is correct.
    $message = '2 additional items can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // Reveals one more element.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    // Checks if the default remaining items help text (singular) is correct.
    $message = '1 additional item can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // Checks if the default button label is correct.
    $message = 'Add another item';
    $this->assertEquals($button->getValue(), $message);

    // Access the settings page and update the help texts and button label.
    $this->drupalGet(Url::fromRoute('sam.admin_settings'));
    $this->submitForm([
      'add_more_label' => 'Add another link',
      'remove_label' => 'Remove',
      'help_text_singular' => '@count additional link can be added',
      'help_text_plural' => '@count additional links can be added',
    ], 'Save configuration');

    // Access the node add page.
    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    // Checks if the new remaining items help text (plural) is correct.
    $message = '2 additional links can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // Reveals one more element.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    // Checks if the new remaining items help text (singular) is correct.
    $message = '1 additional link can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // Checks if the new button label is correct.
    $message = 'Add another link';
    $this->assertEquals($button->getValue(), $message);

  }

}
