<?php

namespace Drupal\easy_email\Event;

final class EasyEmailEvents {

  /**
   * Name of the event fired after creating an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_CREATE = 'easy_email.easy_email.create';

  /**
   * Name of the event fired after saving a new email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_INSERT = 'easy_email.easy_email.insert';

  /**
   * Name of the event fired before saving an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_PRESAVE = 'easy_email.easy_email.presave';

  /**
   * Name of the event fired after saving an existing email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_UPDATE = 'easy_email.easy_email.update';

  /**
   * Name of the event fired before deleting an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_PREDELETE = 'easy_email.easy_email.predelete';

  /**
   * Name of the event fired after deleting an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_DELETE = 'easy_email.easy_email.delete';

  /**
   * Name of the event fired before evaluating tokens in an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_PRETOKENEVAL = 'easy_email.easy_email.pretokeneval';

  /**
   * Name of the event fired after evaluating tokens in an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_TOKENEVAL = 'easy_email.easy_email.tokeneval';

  /**
   * Name of the event fired before evaluating user recipients in an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_PREUSEREVAL = 'easy_email.easy_email.preusereval';

  /**
   * Name of the event fired after evaluating user recipients in an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_USEREVAL = 'easy_email.easy_email.usereval';

  /**
   * Name of the event fired before evaluating attachments in an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_PREATTACHMENTEVAL = 'easy_email.easy_email.preattachmenteval';

  /**
   * Name of the event fired after evaluating attachments in an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_ATTACHMENTEVAL = 'easy_email.easy_email.attachmenteval';

  /**
   * Name of the event fired before sending an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_PRESEND = 'easy_email.easy_email.presend';

  /**
   * Name of the event fired after sending an email.
   *
   * @Event
   *
   * @see \Drupal\easy_email\Event\EasyEmailEvent
   */
  const EMAIL_SENT = 'easy_email.easy_email.sent';

}