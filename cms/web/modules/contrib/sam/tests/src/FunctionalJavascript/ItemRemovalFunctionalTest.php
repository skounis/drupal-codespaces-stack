<?php

namespace Drupal\Tests\sam\FunctionalJavascript;

/**
 * Tests the removal functionality of Simple Add More.
 *
 * @group sam
 */
class ItemRemovalFunctionalTest extends SamFunctionalJavascriptTestBase {

  /**
   * Tests the removal functionality of Simple Add More.
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

    // We have 2 removal buttons.
    $removeButtons = $field_widget->findAll('css', 'table tr.draggable .sam-remove-button');
    $this->assertCount(2, $removeButtons);
    $this->assertFalse($removeButtons[0]->isVisible());
    $this->assertFalse($removeButtons[1]->isVisible());

    // We can reveal one element at a time.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    $removeButtons = $field_widget->findAll('css', 'table tr.draggable .sam-remove-button');
    $this->assertCount(2, $removeButtons);
    $this->assertTrue($removeButtons[0]->isVisible());
    $this->assertFalse($removeButtons[1]->isVisible());

    // The message gets updated accordingly.
    $message = '1 additional item can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);

    // Remove the second one as the first element can not be removed.
    $removeButtons[0]->press();
    $session->wait(200);
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertFalse($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());

    // We add 2 more elements.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $button->press();
    $session->wait(200);
    $button->press();
    $session->wait(200);

    // The button is hidden and no more messages are visible.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $field_widget);
    $this->assertFalse($button->isVisible());
    $message = 'can be added';
    $assert_session->elementTextNotContains('css', '.field--name-field-node__link', $message);

    // Remove the last one.
    $removeButtons[1]->press();
    $session->wait(200);
    $rows = $field_widget->findAll('css', 'table tr.draggable');
    $this->assertCount(3, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());

    // The message gets updated accordingly.
    $message = '1 additional item can be added';
    $assert_session->elementTextContains('css', '.field--name-field-node__link .sam-add-more-help', $message);
  }

}
