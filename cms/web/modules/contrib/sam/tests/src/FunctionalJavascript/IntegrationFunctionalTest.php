<?php

namespace Drupal\Tests\sam\FunctionalJavascript;

/**
 * Tests the basic functionality of Simple Add More.
 *
 * @group sam
 */
class IntegrationFunctionalTest extends SamFunctionalJavascriptTestBase {

  /**
   * Tests the basic functionality of Simple Add More.
   */
  public function testFormSimplification() {
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    // Log in as admin.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer nodes',
      'bypass node access',
      'administer node display',
      'administer display modes',
    ]);
    $this->drupalLogin($this->adminUser);

    // Nothing configured, by default we simplify the node form.
    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    // Only one empty element is visible.
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $value = $assert_session->elementExists('css', 'input[name="field_node__link[0][uri]"]', $rows[0])
      ->getValue();
    $this->assertEmpty($value);
    $this->assertFalse($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    // We see how many more elements can be added.
    $message = '2 additional items can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // We can reveal one element at a time.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    // The message gets updated accordingly.
    $message = '1 additional item can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // Do it again.
    $button->press();
    $session->wait(200);
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertTrue($rows[2]->isVisible());
    // The button is hidden and no more messages are visible.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $this->assertFalse($button->isVisible());
    $message = 'can be added';
    $assert_session->elementTextNotContains('css', '.field--name-field-node__link', $message);
    // If the field is not empty, no empty elements are shown.
    $node = $this->drupalCreateNode([
      'type' => $this->nodeType->id(),
      'title' => 'Node with a link',
      'field_node__link' => [
        'uri' => 'https://drupal.org',
        'text' => 'Drupal dot org',
      ],
    ]);
    $this->drupalGet($node->toUrl('edit-form'));
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $value = $assert_session->elementExists('css', 'input[name="field_node__link[0][uri]"]', $rows[0])
      ->getValue();
    $this->assertNotEmpty($value);
    $this->assertFalse($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    // We see how many more elements can be added.
    $message = '2 additional items can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // We can still reveal more elements.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    // Change the widget third-party settings and verify we skip simplifying.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', $this->nodeType->id(), 'default')
      ->setComponent('field_node__link', [
        'type' => 'link_default',
        'third_party_settings' => [
          'sam' => ['skip_simplification' => TRUE],
        ],
      ])
      ->save();
    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertTrue($rows[2]->isVisible());
    // Switching it back simplifies again.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', $this->nodeType->id(), 'default')
      ->setComponent('field_node__link', [
        'type' => 'link_default',
        'third_party_settings' => [
          'sam' => ['skip_simplification' => FALSE],
        ],
      ])
      ->save();
    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertFalse($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
  }

  public function testFormSimplificationWithAnError() {
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    // Log in as admin.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer nodes',
      'bypass node access',
      'administer node display',
      'administer display modes',
    ]);
    $this->drupalLogin($this->adminUser);

    // Nothing configured, by default we simplify the node form.
    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    $field_widget = $assert_session->elementExists('css', "form .field--name-field-node__link");
    // Only one empty element is visible.
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $value = $assert_session->elementExists('css', 'input[name="field_node__link[0][uri]"]', $rows[0])
      ->getValue();
    $this->assertEmpty($value);
    $this->assertFalse($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    // We see how many more elements can be added.
    $message = '2 additional items can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
    // We can reveal one element at a time.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    // The message gets updated accordingly.
    $message = '1 additional item can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);

    // Submit the form with an invalid url in the second link.
    $session->getPage()->fillField('Title', $this->randomString());
    $session->getPage()->fillField('field_node__link[0][uri]', 'https://drupal.org');
    $session->getPage()->fillField('field_node__link[0][title]', 'Drupal.org');
    $session->getPage()->fillField('field_node__link[1][uri]', 'invalid-url');
    $session->getPage()->find('css', '[value="Save"]')->click();
    $session->wait(200);

    $message = 'Manually entered paths should start with one of the following characters';
    $assert_session->statusMessageContains($message, 'error');

    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    // Second link is still visible.
    $this->assertTrue($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    $message = '1 additional item can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);

    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();

    // Submit the form with an invalid url in the third link.
    $session->getPage()->fillField('Title', $this->randomString());
    $session->getPage()->fillField('field_node__link[1][uri]', 'https://drupal.org');
    $session->getPage()->fillField('field_node__link[1][title]', 'Drupal.org');
    $session->getPage()->fillField('field_node__link[2][uri]', 'invalid-url');
    $session->getPage()->find('css', '[value="Save"]')->click();
    $session->wait(200);

    $message = 'Manually entered paths should start with one of the following characters';
    $assert_session->statusMessageContains($message, 'error');

    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    // Second link is still visible.
    $this->assertTrue($rows[1]->isVisible());
    $this->assertTrue($rows[2]->isVisible());
  }

}
