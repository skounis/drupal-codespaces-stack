<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Traits;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Contains helper methods for testing email sent by Automatic Updates.
 *
 * @internal
 */
trait EmailNotificationsTestTrait {

  use AssertMailTrait;
  use UserCreationTrait;

  /**
   * The people who should be emailed about successful or failed updates.
   *
   * The keys are the email addresses, and the values are the langcode they
   * should be emailed in.
   *
   * @var string[]
   *
   * @see ::setUpEmailRecipients()
   */
  protected $emailRecipients = [];

  /**
   * Prepares the recipient list for emails related to Automatic Updates.
   */
  protected function setUpEmailRecipients(): void {
    // First, create a user whose preferred language is different from the
    // default language, so we can be sure they're emailed in their preferred
    // language; we also ensure that an email which doesn't correspond to a user
    // account is emailed in the default language.
    $default_language = $this->container->get('language_manager')
      ->getDefaultLanguage()
      ->getId();
    $this->assertNotSame('fr', $default_language);

    $account = $this->createUser([], NULL, FALSE, [
      'preferred_langcode' => 'fr',
    ]);
    $this->emailRecipients['emissary@deep.space'] = $default_language;
    $this->emailRecipients[$account->getEmail()] = $account->getPreferredLangcode();

    $this->config('update.settings')
      ->set('notification.emails', array_keys($this->emailRecipients))
      ->save();
  }

  /**
   * Asserts that all recipients received a given email.
   *
   * @param string $expected_subject
   *   The subject line of the email that should have been sent.
   * @param string $expected_body
   *   The beginning of the body text of the email that should have been sent.
   *
   * @see ::$emailRecipients
   */
  protected function assertMessagesSent(string $expected_subject, string $expected_body): void {
    $sent_messages = $this->getMails([
      'subject' => $expected_subject,
    ]);
    $this->assertNotEmpty($sent_messages);
    $this->assertCount(count($this->emailRecipients), $sent_messages);

    // Ensure the body is formatted the way the PHP mailer would do it.
    $expected_message = [
      'body' => [$expected_body],
    ];
    $expected_message = $this->container->get('plugin.manager.mail')
      ->createInstance('php_mail')
      ->format($expected_message);
    $expected_body = $expected_message['body'];

    foreach ($sent_messages as $sent_message) {
      $email = $sent_message['to'];
      $expected_langcode = $this->emailRecipients[$email];

      $this->assertSame($expected_langcode, $sent_message['langcode']);
      // The message, and every line in it, should have been sent in the
      // expected language.
      // @see automatic_updates_test_mail_alter()
      $this->assertArrayHasKey('line_langcodes', $sent_message);
      $this->assertSame([$expected_langcode], $sent_message['line_langcodes']);
      $this->assertStringStartsWith($expected_body, $sent_message['body']);
    }
  }

}
