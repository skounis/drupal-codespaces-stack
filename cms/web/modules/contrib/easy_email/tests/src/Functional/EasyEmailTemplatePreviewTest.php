<?php

namespace Drupal\Tests\easy_email\Functional;

/**
 * Class EasyEmailTemplatePreviewTest
 *
 * @group easy_email
 */
class EasyEmailTemplatePreviewTest extends EasyEmailTestBase {


  /**
   * Tests template preview with all fields
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewAllFields() {
    $template = $this->createTemplate([
      'id' => 'test_all',
      'label' => 'Test: All',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_all');
    $this->assertSession()->pageTextContains('Test: All');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');

    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml(['value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>', 'format' => 'html'])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $html_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()->responseContains('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');
  }


  /**
   * Tests template preview with a HTML body and generating a plain text body
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewHtmlAndGeneratePlain() {
    $template = $this->createTemplate([
      'id' => 'test_html_generate_plain',
      'label' => 'Test: HTML and Generate Plain',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_html_generate_plain');
    $this->assertSession()->pageTextContains('Test: HTML and Generate Plain');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');

    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml(['value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>', 'format' => 'html'])
      ->setGenerateBodyPlain(TRUE)
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $html_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $html_content = $this->getSession()->getPage()->getContent();
    $this->htmlOutput($html_content);
    $this->assertStringContainsString('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.', $html_content);
    $this->assertSession()->responseContains('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $html_content = $this->getSession()->getPage()->getContent();
    $this->htmlOutput($html_content);
    $this->assertSession()->responseContains('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.');
  }

  /**
   * Tests template preview with a HTML body and an inbox preview
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewHtmlWithInboxPreview() {
    $template = $this->createTemplate([
      'id' => 'test_html_inbox_preview',
      'label' => 'Test: HTML and Inbox Preview',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_html_inbox_preview');
    $this->assertSession()->pageTextContains('Test: HTML and Inbox Preview');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');

    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml(['value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>', 'format' => 'html'])
      ->setInboxPreview('This is the inbox preview text for [easy_email:field_user:0:entity:display-name].')
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is the inbox preview text for ' . $user1->getDisplayName() . '.');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $html_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()->responseContains('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');
  }

  /**
   * Tests template preview with only a plain text body and an inbox preview
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewPlainTextOnlyWithInboxPreview() {
    $template = $this->createTemplate([
      'id' => 'test_plain_only_inbox_preview',
      'label' => 'Test: Plain Only and Inbox Preview',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'body_html');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_plain_only_inbox_preview');
    $this->assertSession()->pageTextContains('Test: Plain Only and Inbox Preview');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');

    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setInboxPreview('This is the inbox preview text for [easy_email:field_user:0:entity:display-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (Plain Text) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $this->assertSession()->elementNotExists('css', '[data-drupal-selector="html-body"] iframe');

    $plain_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');
  }

  /**
   * Tests template preview with only a plain text body
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewPlainOnly() {
    $template = $this->createTemplate([
      'id' => 'test_plain_only',
      'label' => 'Test: Plain Only',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'body_html');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_plain_only');
    $this->assertSession()->pageTextContains('Test: Plain Only');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');
    $this->assertSession()->responseNotContains('body_html');
    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (Plain Text) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $this->assertSession()->elementNotExists('css', '[data-drupal-selector="html-body"] iframe');
    $plain_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');
  }

  /**
   * Tests template preview with only a HTML body
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewHtmlOnly() {
    $template = $this->createTemplate([
      'id' => 'test_html_only',
      'label' => 'Test: HTML Only',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'body_plain');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_html_only');
    $this->assertSession()->pageTextContains('Test: HTML Only');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');
    $this->assertSession()->responseNotContains('body_plain');
    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml(['value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>', 'format' => 'html'])
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $html_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $this->assertSession()->elementNotExists('css', '[data-drupal-selector="plain-body"] iframe');

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()->responseContains('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.');
  }

  /**
   * Tests template preview with unsafe tokens
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreviewUnsafeTokens() {
    $template = $this->createTemplate([
      'id' => 'test_unsafe_tokens',
      'label' => 'Test: Unsafe Tokens',
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains('test_unsafe_tokens');
    $this->assertSession()->pageTextContains('Test: Unsafe Tokens');
    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/edit/fields');
    $template->setRecipient(['test@example.com', '[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]')
      ->setBodyHtml(['value' => '<p>Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]</p>', 'format' => 'html'])
      ->setBodyPlain('Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]')
      ->setInboxPreview('Preview: Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/structure/email-templates/templates/' . $template->id(). '/preview');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Preview');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'Preview: Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]');
    $this->assertSession()->linkByHrefExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));

    $html_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()->responseContains('<p>Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()->responseContains('Cancel: [easy_email:field_user:0:entity:cancel-url], Login: [easy_email:field_user:0:entity:one-time-login-url]');
  }

}
