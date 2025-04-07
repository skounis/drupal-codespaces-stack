<?php

namespace Drupal\Tests\easy_email\Functional;

/**
 * Class EasyEmailSendTest
 *
 * @group easy_email
 */
class EasyEmailSendTest extends EasyEmailTestBase {

  /**
   * Tests sending email with an HTML and Plain Text version
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendHtmlAndPlainText() {
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

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertContains($user2->id(), $email_entity->getCCIds());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertContains($user3->id(), $email_entity->getBCCIds());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertContains($user1->id(), $email_entity->getRecipientIds());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()
      ->linkByHrefNotExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));
    $this->assertSession()->pageTextNotContains('Attachments');

    $html_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()
      ->responseContains('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()
      ->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');
    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail(), $email['headers']['Cc']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail(), $email['headers']['Bcc']);
    $this->assertEquals($site_config->get('name') . ' <' . $site_config->get('mail') . '>', $email['headers']['From']);
    $this->assertEquals($site_config->get('mail'), $email['headers']['Sender']);
    $this->assertArrayNotHasKey('Reply-To', $email['headers']);
    $this->assertEquals('text/html; charset=UTF-8;', $email['headers']['Content-Type']);
    $this->assertStringContainsString('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>', (string) $email['body']);
    $this->assertStringContainsString('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.', (string) $email['plain']);
    $this->assertEquals('Test email for ' . $user1->getDisplayName(), $email['subject']);
    $this->assertEquals(1, count($email['params']['files']));
    $attachment = reset($email['params']['files']);
    $this->assertEquals('core/misc/druplicon.png', $attachment->uri);
    $this->assertEquals('druplicon.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
  }

  /**
   * Tests email saving without sending.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSaveWithoutSend() {
    $template_id = 'test_no_send';
    $template_label = 'Test: No Send';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
      'send' => FALSE,
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextNotContains('Email sent.');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()
      ->linkByHrefNotExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));
    $this->assertSession()->pageTextNotContains('Attachments');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);

    $html_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()
      ->responseContains('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()
      ->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');

    $emails = $this->getSentEmails([]);
    $this->assertEquals(0, count($emails));

    $this->drupalGet('admin/content/email/' . $email_id . '/edit');
    $this->submitForm([
      'send' => TRUE,
    ], 'Save');

    $this->assertSession()->pageTextContains('Saved email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail(), $email['headers']['Cc']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail(), $email['headers']['Bcc']);
    $this->assertEquals($site_config->get('name') . ' <' . $site_config->get('mail') . '>', $email['headers']['From']);
    $this->assertEquals($site_config->get('mail'), $email['headers']['Sender']);
    $this->assertArrayNotHasKey('Reply-To', $email['headers']);
    $this->assertEquals('text/html; charset=UTF-8;', $email['headers']['Content-Type']);
    $this->assertStringContainsString('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>', (string) $email['body']);
    $this->assertStringContainsString('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.', (string) $email['plain']);
    $this->assertEquals('Test email for ' . $user1->getDisplayName(), $email['subject']);
    $this->assertEquals(1, count($email['params']['files']));
    $attachment = reset($email['params']['files']);
    $this->assertEquals('core/misc/druplicon.png', $attachment->uri);
    $this->assertEquals('druplicon.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
  }

  /**
   * Tests email sending with customized text at send time.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithCustomizedEmail() {
    $template_id = 'test_customized';
    $template_label = 'Test: Customized';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setAttachment(['/core/misc/druplicon.png'])
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
      'recipient' => 'test@example.com, [easy_email:field_user:0:entity:mail], overridden-recipient@example.com',
      'cc' => 'cc@example.com, [easy_email:field_cc_user:0:entity:mail], overridden-cc@example.com',
      'bcc' => 'bcc@example.com, [easy_email:field_bcc_user:0:entity:mail], overridden-bcc@example.com',
      'bodyHtml[value]' => '<p>This is the overridden HTML body for user account [easy_email:field_user:0:entity:account-name].</p>',
      'inboxPreview' => 'This is the overridden inbox preview for user account [easy_email:field_user:0:entity:account-name].',
      'bodyPlain' => 'This is the overridden plain text body for user account [easy_email:field_user:0:entity:account-name].',
      'subjectText' => 'Overridden subject for [easy_email:field_user:0:entity:display-name]',
      'fromName' => 'Overridden Person',
      'fromAddress' => 'overridden@example.com',
      'replyToAddress' => 'override-reply-to@example.com',
      'attachment_paths' => '/core/misc/druplicon.png, /core/misc/help.png',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', 'Overridden Person <overridden@example.com>');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'overridden-cc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'overridden-bcc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'overridden-recipient@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', 'Overridden Person');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Overridden subject for ' . $user1->getDisplayName());
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is the overridden inbox preview for user account ' . $user1->getDisplayName() . '.');
    $this->assertSession()
      ->linkByHrefNotExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));
    $this->assertSession()
      ->linkByHrefNotExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/help.png'));
    $this->assertSession()->pageTextNotContains('Attachments');

    $html_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()
      ->responseContains('<p>This is the overridden HTML body for user account ' . $user1->getAccountName() . '.</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()
      ->responseContains('This is the overridden plain text body for user account ' . $user1->getAccountName() . '.');
    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail() . ', overridden-recipient@example.com', $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail() . ', overridden-cc@example.com', $email['headers']['Cc']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail() . ', overridden-bcc@example.com', $email['headers']['Bcc']);
    $this->assertEquals('Overridden Person <overridden@example.com>', $email['headers']['From']);
    $this->assertEquals($site_config->get('mail'), $email['headers']['Sender']);
    $this->assertEquals('override-reply-to@example.com', $email['headers']['Reply-to']);
    $this->assertEquals('text/html; charset=UTF-8;', $email['headers']['Content-Type']);
    $this->assertStringContainsString('<p>This is the overridden HTML body for user account ' . $user1->getAccountName() . '.</p>', (string) $email['body']);
    $this->assertStringContainsString('This is the overridden inbox preview for user account ' . $user1->getAccountName() . '.', (string) $email['body']);
    $this->assertStringContainsString('This is the overridden plain text body for user account ' . $user1->getAccountName() . '.', (string) $email['plain']);
    $this->assertEquals('Overridden subject for ' . $user1->getDisplayName(), $email['subject']);
    $this->assertEquals(2, count($email['params']['files']));
    $attachment = array_shift($email['params']['files']);
    $this->assertEquals('core/misc/druplicon.png', $attachment->uri);
    $this->assertEquals('druplicon.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
    $attachment = array_shift($email['params']['files']);
    $this->assertEquals('core/misc/help.png', $attachment->uri);
    $this->assertEquals('help.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
  }

  /**
   * Tests email sending with plain text version generated from HTML version
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendHtmlGeneratePlainText() {
    $template_id = 'test_plain_text_generated';
    $template_label = 'Test: Plain Text Generated';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['test@example.com'])
      ->setSubject('Test Email with Generated Plain Text')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setGenerateBodyPlain(TRUE)
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');

    $html_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()
      ->responseContains('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()
      ->responseContains('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.');
    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertStringContainsString('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>', (string) $email['body']);
    $this->assertStringContainsString('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.', (string) $email['plain']);
  }

  /**
   * Tests email sending with plain text version only
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendPlainOnly() {
    $template_id = 'test_plain_text_only';
    $template_label = 'Test: Plain Text Only';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->removeField($template, 'body_html');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['test@example.com'])
      ->setSubject('Test Email with Generated Plain Text')
      // Put body HTML in here to make sure it's not being used.
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (Plain Text) for user account ' . $user1->getDisplayName() . '.');

    $this->assertSession()
      ->elementNotExists('css', '[data-drupal-selector="html-body"] iframe');
    $plain_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()
      ->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.');
    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertStringNotContainsString('This is a test email (HTML) for user account', (string) $email['body']);
    $this->assertStringContainsString('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.', (string) $email['body']);
    $this->assertArrayNotHasKey('plain', $email);
  }

  /**
   * Tests email sending with HTML version only
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendHtmlOnly() {
    $template_id = 'test_html_only';
    $template_label = 'Test: HTML Only';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->removeField($template, 'body_plain');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['test@example.com'])
      ->setSubject('Test Email with Generated Plain Text')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      // Put plain text body in here to make sure it's not being used.
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is a test email (HTML) for user account ' . $user1->getDisplayName() . '.');

    $html_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $this->assertSession()
      ->elementNotExists('css', '[data-drupal-selector="plain-body"] iframe');

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()
      ->responseContains('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.</p>');

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertStringNotContainsString('This is a test email (Plain Text) for user account', (string) $email['body']);
    $this->assertStringContainsString('This is a test email (HTML) for user account ' . $user1->getAccountName() . '.', (string) $email['body']);
    $this->assertArrayNotHasKey('plain', $email);
  }

  /**
   * Tests email sending without a CC address field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithoutCcAddress() {
    $template_id = 'test_without_cc_address';
    $template_label = 'Test: Without CC Address';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'cc_address');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementNotExists('css', '[data-drupal-selector="header-Cc"]');
    $this->assertNotContains($user2->id(), $email_entity->getCCIds());
    $this->assertContains($user3->id(), $email_entity->getBCCIds());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail(), $email['headers']['Bcc']);
    $this->assertArrayNotHasKey('Cc', $email['headers']);
  }

  /**
   * Tests email sending without a CC UID field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithoutCcUid() {
    $template_id = 'test_without_cc_uid';
    $template_label = 'Test: Without CC UID';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'cc_uid');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', 'cc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Cc"] span.value', $user2->getEmail());
    $this->assertNotContains($user2->id(), $email_entity->getCCIds());
    $this->assertEmpty($email_entity->getCCIds());
    $this->assertContains($user3->id(), $email_entity->getBCCIds());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail(), $email['headers']['Cc']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail(), $email['headers']['Bcc']);
  }


  /**
   * Tests email sending without a BCC address field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithoutBccAddress() {
    $template_id = 'test_without_bcc_address';
    $template_label = 'Test: Without BCC Address';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'bcc_address');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementNotExists('css', '[data-drupal-selector="header-Bcc"]');
    $this->assertContains($user2->id(), $email_entity->getCCIds());
    $this->assertNotContains($user3->id(), $email_entity->getBCCIds());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail(), $email['headers']['Cc']);
    $this->assertArrayNotHasKey('Bcc', $email['headers']);
  }

  /**
   * Tests email sending without a BCC UID field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithoutBccUid() {
    $template_id = 'test_without_bcc_uid';
    $template_label = 'Test: Without BCC UID';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'bcc_uid');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', 'bcc@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Bcc"] span.value', $user3->getEmail());
    $this->assertContains($user2->id(), $email_entity->getCCIds());
    $this->assertNotContains($user3->id(), $email_entity->getBCCIds());
    $this->assertEmpty($email_entity->getBCCIds());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail(), $email['headers']['Cc']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail(), $email['headers']['Bcc']);
  }

  /**
   * Tests email sending without a Recipient UID field
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithoutRecipientUid() {
    $template_id = 'test_without_recipient_uid';
    $template_label = 'Test: Without Recipient UID';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');
    $this->removeField($template, 'recipient_uid');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', 'test@example.com');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-To"] span.value', $user1->getEmail());
    $this->assertNotContains($user1->id(), $email_entity->getRecipientIds());
    $this->assertEmpty($email_entity->getRecipientIds());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals('test@example.com, ' . $user1->getEmail(), $email['to']);
    $this->assertEquals('cc@example.com, ' . $user2->getEmail(), $email['headers']['Cc']);
    $this->assertEquals('bcc@example.com, ' . $user3->getEmail(), $email['headers']['Bcc']);
  }

  /**
   * Tests email sending with attachments that are not saved to the log.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithAttachmentsNotSaved() {
    $template_id = 'test_attachments_not_saved';
    $template_label = 'Test: Attachments Not Saved';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setSaveAttachment(FALSE)
      ->setAttachment(['/core/misc/druplicon.png', '/core/misc/help.png'])
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()
      ->linkByHrefNotExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/druplicon.png'));
    $this->assertSession()
      ->linkByHrefNotExists(\Drupal::service('file_url_generator')->generateAbsoluteString('/core/misc/help.png'));
    $this->assertSession()->pageTextNotContains('Attachments');

    $this->assertEmpty($email_entity->getAttachmentIds());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals(2, count($email['params']['files']));
    $attachment = array_shift($email['params']['files']);
    $this->assertEquals('core/misc/druplicon.png', $attachment->uri);
    $this->assertEquals('druplicon.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
    $attachment = array_shift($email['params']['files']);
    $this->assertEquals('core/misc/help.png', $attachment->uri);
    $this->assertEquals('help.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
  }

  /**
   * Tests email sending with attachments that are saved.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithAttachmentsSaved() {
    $template_id = 'test_attachments_saved';
    $template_label = 'Test: Attachments Saved';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient([
      'test@example.com',
      '[easy_email:field_user:0:entity:mail]'
    ])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setSaveAttachment(TRUE)
      ->setAttachmentScheme('private')
      ->setAttachment(['/core/misc/druplicon.png', '/core/misc/help.png'])
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
    ], 'Save');

    $url = explode('/', $this->getSession()->getCurrentUrl());
    $email_id = array_pop($url);
    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $email_entity */
    \Drupal::entityTypeManager()->getStorage('easy_email')->resetCache();
    $email_entity = \Drupal::entityTypeManager()
      ->getStorage('easy_email')
      ->load($email_id);

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    $this->assertSession()->pageTextContains('Attachments');
    $this->assertSession()->linkExists('druplicon.png');
    $this->assertSession()->linkExists('help.png');

    $attachments = $email_entity->getAttachments();
    $this->assertEquals(2, count($attachments));
    /** @var \Drupal\file\FileInterface $attachment */
    $attachment = array_shift($attachments);
    $this->assertEquals('druplicon.png', $attachment->getFilename());
    $this->assertEquals('image/png', $attachment->getMimeType());
    $attachment = array_shift($attachments);
    $this->assertEquals('help.png', $attachment->getFilename());
    $this->assertEquals('image/png', $attachment->getMimeType());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
    $email = reset($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals(2, count($email['params']['files']));
    $attachment = array_shift($email['params']['files']);
    $this->assertEquals('core/misc/druplicon.png', $attachment->uri);
    $this->assertEquals('druplicon.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
    $attachment = array_shift($email['params']['files']);
    $this->assertEquals('core/misc/help.png', $attachment->uri);
    $this->assertEquals('help.png', $attachment->filename);
    $this->assertEquals('image/png', $attachment->filemime);
  }

  /**
   * Tests email sending with unsafe tokens.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendWithUnsafeTokens() {
    $template_id = 'test_unsafe_tokens';
    $template_label = 'Test: Unsafe Tokens';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->addUserField($template, 'field_cc_user', 'CC User');
    $this->addUserField($template, 'field_bcc_user', 'BCC User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['[easy_email:field_user:0:entity:mail]'])
      ->setCc(['cc@example.com', '[easy_email:field_cc_user:0:entity:mail]'])
      ->setBcc(['bcc@example.com', '[easy_email:field_bcc_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]: [easy_email:field_user:0:entity:cancel-url], [easy_email:field_cc_user:0:entity:one-time-login-url]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name]. [easy_email:field_cc_user:0:entity:cancel-url], [easy_email:field_bcc_user:0:entity:one-time-login-url]</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name]. [easy_email:cc_uid:0:entity:cancel-url], [easy_email:bcc_uid:0:entity:one-time-login-url]')
      ->setInboxPreview('This is the inbox preview for user account [easy_email:field_user:0:entity:account-name]. [easy_email:recipient_uid:1:entity:cancel-url], [easy_email:recipient_uid:1:entity:one-time-login-url]')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();
    $user4 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
      'field_cc_user[0][target_id]' => $user2->getAccountName() . ' (' . $user2->id() . ')',
      'field_bcc_user[0][target_id]' => $user3->getAccountName() . ' (' . $user3->id() . ')',
      'recipient' => '[easy_email:field_user:0:entity:mail], ' . $user4->getEmail(),
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');

    /** @var \Drupal\Core\Config\ImmutableConfig $site_config */
    $site_config = \Drupal::config('system.site');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Return-Path"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Sender"] span.value', $site_config->get('mail'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-From"] span.value', $site_config->get('name') . ' <' . $site_config->get('mail') . '>');
    // CC and BCC should be removed.
    $this->assertSession()
      ->elementNotExists('css', '[data-drupal-selector="header-Cc"] span.value');
    $this->assertSession()
      ->elementNotExists('css', '[data-drupal-selector="header-Bcc"] span.value');
    // The message has been split up because of 2 recipients, so let's skip To header for now. Check actual sent messages
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="header-Subject"] span.value', 'Test email for ' . $user1->getDisplayName() . ': [easy_email:field_user:0:entity:cancel-url], [easy_email:field_cc_user:0:entity:one-time-login-url]');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .from-name', $site_config->get('name'));
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .subject', 'Test email for ' . $user1->getDisplayName() . ': [easy_email:field_user:0:entity:cancel-url], [easy_email:field_cc_user:0:entity:one-time-login-url]');
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-selector="inbox-preview"] .body-preview', 'This is the inbox preview for user account ' . $user1->getDisplayName() . '. [easy_email:recipient_uid:1:entity:cancel-url], [easy_email:recipient_uid:1:entity:one-time-login-url]');

    $html_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="html-body"] iframe');
    $html_body_url = $this->getIframeUrlAndQuery($html_body_iframe);
    $plain_body_iframe = $this->assertSession()
      ->elementExists('css', '[data-drupal-selector="plain-body"] iframe');
    $plain_body_url = $this->getIframeUrlAndQuery($plain_body_iframe);

    $this->drupalGet($html_body_url['path'], ['query' => $html_body_url['query']]);
    $this->assertSession()
      ->responseContains('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '. [easy_email:field_cc_user:0:entity:cancel-url], [easy_email:field_bcc_user:0:entity:one-time-login-url]</p>');

    $this->drupalGet($plain_body_url['path'], ['query' => $plain_body_url['query']]);
    $this->assertSession()
      ->responseContains('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '. [easy_email:cc_uid:0:entity:cancel-url], [easy_email:bcc_uid:0:entity:one-time-login-url]');

    $emails = $this->getSentEmails([]);
    $this->assertEquals(2, count($emails));
    // There are 2 emails, one for each recipient.

    $emails = $this->getSentEmails(['to' => $user1->getEmail()]);
    $this->assertEquals(1, count($emails));
    $email = array_shift($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals($user1->getEmail(), $email['to']);

    // CC and BCC have been stripped out.
    $this->assertArrayNotHasKey('Cc', $email['headers']);
    $this->assertArrayNotHasKey('Bcc', $email['headers']);

    // Should have standard tokens evaluated, but unsafe tokens always evaluated for the recipient user.
    $this->assertStringContainsString('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.', (string) $email['body']);
    $this->assertStringContainsString('/user/' . $user1->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringContainsString('/user/reset/' . $user1->id() . '/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/' . $user4->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/reset/' . $user4->id() . '/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/' . $user2->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/reset/' . $user2->id() . '/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/' . $user3->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/reset/' . $user3->id() . '/', (string) $email['body']);
    $this->assertStringContainsString('This is the inbox preview for user account ' . $user1->getDisplayName() . '.', (string) $email['body']);

    $this->assertStringContainsString('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.', (string) $email['plain']);
    $this->assertStringContainsString('/user/' . $user1->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringContainsString('/user/reset/' . $user1->id() . '/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/' . $user4->id() . '/cancel/confirm/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/reset/' . $user4->id() . '/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/' . $user2->id() . '/cancel/confirm/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/reset/' . $user2->id() . '/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/' . $user3->id() . '/cancel/confirm/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/reset/' . $user3->id() . '/', (string) $email['plain']);

    // Unsafe tokens skipped in subject
    $this->assertEquals('Test email for ' . $user1->getDisplayName() . ': [easy_email:field_user:0:entity:cancel-url], [easy_email:field_cc_user:0:entity:one-time-login-url]', $email['subject']);

    $emails = $this->getSentEmails(['to' => $user4->getEmail()]);
    $this->assertEquals(1, count($emails));
    $email = array_shift($emails);
    $this->assertEquals($template->id(), $email['key']);
    $this->assertEquals($user4->getEmail(), $email['to']);

    // CC and BCC have been stripped out.
    $this->assertArrayNotHasKey('Cc', $email['headers']);
    $this->assertArrayNotHasKey('Bcc', $email['headers']);

    // Should have standard tokens evaluated, but unsafe tokens always evaluated for the recipient user.
    $this->assertStringContainsString('<p>This is a test email (HTML) for user account ' . $user1->getAccountName() . '.', (string) $email['body']);
    $this->assertStringContainsString('/user/' . $user4->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringContainsString('/user/reset/' . $user4->id() . '/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/' . $user1->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/reset/' . $user1->id() . '/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/' . $user2->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/reset/' . $user2->id() . '/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/' . $user3->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringNotContainsString('/user/reset/' . $user3->id() . '/', (string) $email['body']);
    $this->assertStringContainsString('This is the inbox preview for user account ' . $user1->getDisplayName() . '.', (string) $email['body']);

    $this->assertStringContainsString('This is a test email (Plain Text) for user account ' . $user1->getAccountName() . '.', (string) $email['plain']);
    $this->assertStringContainsString('/user/' . $user4->id() . '/cancel/confirm/', (string) $email['body']);
    $this->assertStringContainsString('/user/reset/' . $user4->id() . '/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/' . $user1->id() . '/cancel/confirm/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/reset/' . $user1->id() . '/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/' . $user2->id() . '/cancel/confirm/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/reset/' . $user2->id() . '/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/' . $user3->id() . '/cancel/confirm/', (string) $email['plain']);
    $this->assertStringNotContainsString('/user/reset/' . $user3->id() . '/', (string) $email['plain']);

    // Unsafe tokens skipped in subject
    $this->assertEquals('Test email for ' . $user1->getDisplayName() . ': [easy_email:field_user:0:entity:cancel-url], [easy_email:field_cc_user:0:entity:one-time-login-url]', $email['subject']);

  }

  /**
   * Tests email sending with a unique key to prevent duplicates
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendDuplicateCheck() {
    $template_id = 'test_unique_key';
    $template_label = 'Test: Unique Key';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['[easy_email:field_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setInboxPreview('This is the inbox preview for user account [easy_email:field_user:0:entity:account-name].')
      ->setKey('test1:::[easy_email:field_user:0:target_id]')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');


    $this->drupalGet('admin/content/email/add/' . $template->id());
    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextNotContains('Created new email.');
    $this->assertSession()->pageTextNotContains('Email sent.');
    $this->assertSession()->pageTextContains('Email matching unique key already exists.');
    $this->assertSession()->addressEquals('admin/content/email/add/' . $template->id());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(1, count($emails));
  }

  /**
   * Tests email sending without a unique key to prevent duplicates
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendNoDuplicateCheck() {
    $template_id = 'test_without_unique_key';
    $template_label = 'Test: Without Unique Key';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['[easy_email:field_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setInboxPreview('This is the inbox preview for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');


    $this->drupalGet('admin/content/email/add/' . $template->id());
    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');;
    $this->assertSession()->pageTextNotContains('Email matching unique key already exists.');
    $this->assertSession()->addressNotEquals('admin/content/email/add/' . $template->id());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(2, count($emails));
  }

  /**
   * Tests email sending without a unique key field to prevent duplicates
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSendNoKeyField() {
    $template_id = 'test_without_unique_key';
    $template_label = 'Test: Without Unique Key';
    $template = $this->createTemplate([
      'id' => $template_id,
      'label' => $template_label,
    ]);
    $this->addUserField($template, 'field_user', 'User');
    $this->removeField($template, 'key');

    $this->drupalGet('admin/structure/email-templates/templates');
    $this->assertSession()->pageTextContains($template_id);
    $this->assertSession()->pageTextContains($template_label);

    $template->setRecipient(['[easy_email:field_user:0:entity:mail]'])
      ->setSubject('Test email for [easy_email:field_user:0:entity:display-name]')
      ->setBodyHtml([
        'value' => '<p>This is a test email (HTML) for user account [easy_email:field_user:0:entity:account-name].</p>',
        'format' => 'html'
      ])
      ->setBodyPlain('This is a test email (Plain Text) for user account [easy_email:field_user:0:entity:account-name].')
      ->setInboxPreview('This is the inbox preview for user account [easy_email:field_user:0:entity:account-name].')
      ->save();

    $this->drupalGet('admin/content/email/add/' . $template->id());

    $user1 = $this->createUser();

    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');


    $this->drupalGet('admin/content/email/add/' . $template->id());
    $this->submitForm([
      'field_user[0][target_id]' => $user1->getAccountName() . ' (' . $user1->id() . ')',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created new email.');
    $this->assertSession()->pageTextContains('Email sent.');;
    $this->assertSession()->pageTextNotContains('Email matching unique key already exists.');
    $this->assertSession()->addressNotEquals('admin/content/email/add/' . $template->id());

    $emails = $this->getSentEmails([]);
    $this->assertEquals(2, count($emails));
  }
}
