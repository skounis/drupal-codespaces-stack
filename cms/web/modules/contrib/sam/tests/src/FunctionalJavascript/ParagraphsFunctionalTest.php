<?php

namespace Drupal\Tests\sam\FunctionalJavascript;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests the Simple Add More functionality inside paragraphs.
 *
 * @group sam
 */
class ParagraphsFunctionalTest extends SamFunctionalJavascriptTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'entity_reference_revisions',
    'field',
    'field_ui',
    'node',
    'system',
    'sam',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a paragraph with a limited-cardinality text field and add it to
    // our test content type.
    $this->addParagraphsType("paragraph_sam");
    // cSpell:ignore Fieldto
    $this->addFieldtoParagraphType("paragraph_sam", "field_text", 'string');
    $this->addParagraphsField($this->nodeType->id(), 'field_paragraphs', 'node');
    FieldStorageConfig::loadByName('paragraph', 'field_text')
      ->setCardinality(4)
      ->save();
    // We want a second paragraph type available just so that the widget doesn't
    // pre-populate the form with our type open on page load.
    $this->addParagraphsType('paragraph_foo');
  }

  /**
   * Tests that we simplify as expected elements inside paragraphs.
   */
  public function testParagraphElements() {
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

    $this->drupalGet("/node/add/{$this->nodeType->id()}");
    $assert_session->pageTextContains("Create {$this->nodeType->label()}");
    // Add a paragraph item to the node form.
    $assert_session->elementExists('css', 'input[name="field_paragraphs_paragraph_sam_add_more"]')
      ->click();
    $subform = $assert_session->waitForElementVisible('css', '.ajax-new-content .paragraphs-subform[data-drupal-selector="edit-field-paragraphs-0-subform"]');
    // Only one empty element is visible.
    $rows = $subform->findAll('css', 'table tr.draggable');
    $this->assertCount(4, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $value = $assert_session->elementExists('css', 'input[name="field_paragraphs[0][subform][field_text][0][value]"]', $rows[0])
      ->getValue();
    $this->assertEmpty($value);
    $this->assertFalse($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    $this->assertFalse($rows[3]->isVisible());
    // We see how many more elements can be added.
    $message = '3 additional items can be added';
    $assert_session->elementTextContains('css', '[data-drupal-selector="edit-field-paragraphs-0-subform-field-text"] .sam-add-more-help', $message);
    // We can reveal one element at a time.
    $button = $assert_session->elementExists('css', '.sam-add-more-button', $subform);
    $button->press();
    $session->wait(200);
    $rows = $subform->findAll('css', 'table tr.draggable');
    $this->assertCount(4, $rows);
    $this->assertTrue($rows[0]->isVisible());
    $this->assertTrue($rows[1]->isVisible());
    $this->assertFalse($rows[2]->isVisible());
    $this->assertFalse($rows[3]->isVisible());
    // The message gets updated accordingly.
    $message = '2 additional items can be added';
    $assert_session->elementTextContains('css', '[data-drupal-selector="edit-field-paragraphs-0-subform-field-text"] .sam-add-more-help', $message);
  }

}
