<?php

namespace Drupal\Tests\easy_email\Functional;


/**
 * Class EasyEmailTemplateCreateTest
 *
 * @group easy_email
 */
class EasyEmailTemplateCreateTest extends EasyEmailTestBase {

  /**
   * Tests template create/edit form with default fields
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateDefaultsTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_defaults',
      'label' => 'Test: Defaults',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_defaults');
    $this->assertSession()->pageTextContains('Test: Defaults');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: Defaults');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->getSession()->getPage()->fillField('Unique Key Pattern', '[easy_email:recipient_address:0:value]:test_defaults');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->getSession()->getPage()->fillField('To', 'recipient@example.com');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->getSession()->getPage()->fillField('edit-cc', 'cc@example.com');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->getSession()->getPage()->fillField('edit-bcc', 'bcc@example.com');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->getSession()->getPage()->fillField('From Name', 'Testing Easy Email');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->getSession()->getPage()->fillField('From Address', 'from@example.com');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->getSession()->getPage()->fillField('Reply To Address', 'replyto@example.com');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->getSession()->getPage()->fillField('Subject', 'This is the subject');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->getSession()->getPage()->fillField('HTML Body', '<p>This is the HTML body.</p>');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->getSession()->getPage()->fillField('Inbox Preview', 'This is the inbox preview.');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->getSession()->getPage()->fillField('Plain Text Body', 'This is the plain text body.');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->getSession()->getPage()->fillField('Dynamic Attachments', 'web/core/misc/drupalicon.png');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->getSession()->getPage()->fillField('File Directory', 'email-attachments');
    $this->assertSession()->linkExists('Browse available tokens.');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Test: Defaults Email type');

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: Defaults');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '[easy_email:recipient_address:0:value]:test_defaults');
    $this->assertSession()->fieldValueEquals('To', 'recipient@example.com');
    $this->assertSession()->fieldValueEquals('edit-cc', 'cc@example.com');
    $this->assertSession()->fieldValueEquals('edit-bcc', 'bcc@example.com');
    $this->assertSession()->fieldValueEquals('From Name', 'Testing Easy Email');
    $this->assertSession()->fieldValueEquals('From Address', 'from@example.com');
    $this->assertSession()->fieldValueEquals('Reply To Address', 'replyto@example.com');
    $this->assertSession()->fieldValueEquals('Subject', 'This is the subject');
    $this->assertSession()->fieldValueEquals('HTML Body', '<p>This is the HTML body.</p>');
    $this->assertSession()->fieldValueEquals('Inbox Preview', 'This is the inbox preview.');
    $this->assertSession()->fieldValueEquals('Plain Text Body', 'This is the plain text body.');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', 'web/core/misc/drupalicon.png');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', 'email-attachments');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no plain text body field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoPlainTextTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_plain_text',
      'label' => 'Test: No Plain Text',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.body_plain/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Plain Text');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementNotExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldNotExists('Plain Text Body');
    $this->assertSession()->fieldNotExists('generateBodyPlain');
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');

  }

  /**
   * Tests template create/edit form with no HTML body field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoHtmlTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_html_body',
      'label' => 'Test: No HTML Body',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.body_html/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No HTML Body');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementNotExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldNotExists('HTML Body');
    $this->assertSession()->fieldNotExists('Inbox Preview');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldNotExists('generateBodyPlain');
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no recipient reference field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoRecipientsTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_recipient_uid',
      'label' => 'Test: No Recipient Reference',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.recipient_uid/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Recipient Reference');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no CC reference field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoCCUidTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_cc_uid',
      'label' => 'Test: No CC Reference',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.cc_uid/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No CC Reference');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no CC text field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoCCAddressTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_cc_address',
      'label' => 'Test: No CC Address',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.cc_address/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No CC Address');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldNotExists('edit-cc');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no BCC reference field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoBCCUidTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_bcc_uid',
      'label' => 'Test: No BCC Reference',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.bcc_uid/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No BCC Reference');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no BCC text field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoBCCAddressTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_bcc_address',
      'label' => 'Test: No BCC Address',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.bcc_address/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No BCC Address');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldNotExists('edit-bcc');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no From Name field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoFromNameTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_from_name',
      'label' => 'Test: No From Name',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.from_name/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No From Name');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldNotExists('From Name');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no From Address field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoFromAddressTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_from_address',
      'label' => 'Test: No From Address',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.from_address/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No From Address');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldNotExists('From Address');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no Reply To field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoReplyToAddressTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_reply_to',
      'label' => 'Test: No Reply To',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.reply_to/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Reply To');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldNotExists('Reply To Address');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no Reply To field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoFromOrReplyToAddressTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_from_or_reply_to',
      'label' => 'Test: No From or Reply To',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.reply_to/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.from_address/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.from_name/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No From or Reply To');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->elementNotExists('css', 'fieldset[data-drupal-selector="edit-sender"]');
    $this->assertSession()->fieldNotExists('From Name');
    $this->assertSession()->fieldNotExists('From Address');
    $this->assertSession()->fieldNotExists('Reply To Address');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no dynamic attachments field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoDynamicAttachmentsTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_attachment_path',
      'label' => 'Test: No Dynamic Attachments',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.attachment_path/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Dynamic Attachments');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldNotExists('Dynamic Attachments');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no attachments file reference field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoAttachmentFilesTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_attachment',
      'label' => 'Test: No Attachments',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.attachment/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Attachments');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldNotExists('saveAttachment');
    $this->assertSession()->fieldNotExists('attachmentScheme');
    $this->assertSession()->fieldNotExists('File Directory');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no inbox preview field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoInboxPreviewTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_inbox_preview',
      'label' => 'Test: No Inbox Preview',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.inbox_preview/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Inbox Preview');
    $this->assertSession()->fieldValueEquals('Unique Key Pattern', '');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldNotExists('Inbox Preview');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

  /**
   * Tests template create/edit form with no unique key field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCreateNoUniqueKeyTemplate() {
    $template = $this->createTemplate([
      'id' => 'test_no_unique_key',
      'label' => 'Test: No Unique Key',
    ]);
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id() . '/edit/fields/easy_email.' . $template->id() . '.key/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit');
    $this->assertSession()->fieldValueEquals('Label', 'Test: No Unique Key');
    $this->assertSession()->fieldNotExists('Unique Key Pattern');
    $this->assertSession()->fieldValueEquals('recipient', '');
    $this->assertSession()->fieldValueEquals('edit-cc', '');
    $this->assertSession()->fieldValueEquals('edit-bcc', '');
    $this->assertSession()->fieldValueEquals('From Name', '');
    $this->assertSession()->fieldValueEquals('From Address', '');
    $this->assertSession()->fieldValueEquals('Reply To Address', '');
    $this->assertSession()->fieldValueEquals('Subject', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-html"]');
    $this->assertSession()->fieldValueEquals('HTML Body', '');
    $this->assertSession()->fieldValueEquals('Inbox Preview', '');
    $this->assertSession()->elementExists('css', 'details[data-drupal-selector="edit-body-plain"]');
    $this->assertSession()->fieldValueEquals('Plain Text Body', '');
    $this->assertSession()->fieldValueEquals('generateBodyPlain', FALSE);
    $this->assertSession()->fieldValueEquals('Dynamic Attachments', '');
    $this->assertSession()->fieldValueEquals('saveAttachment', FALSE);
    $this->assertSession()->fieldValueEquals('attachmentScheme', 'private');
    $this->assertSession()->fieldValueEquals('File Directory', '');
    $this->assertSession()->linkExists('Browse available tokens.');
  }

}