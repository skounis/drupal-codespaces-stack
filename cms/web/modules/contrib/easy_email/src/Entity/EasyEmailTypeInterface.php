<?php

namespace Drupal\easy_email\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Email template entities.
 */
interface EasyEmailTypeInterface extends ConfigEntityInterface {


  /**
   * @return string
   */
  public function getId();

  /**
   * @param string $id
   *
   * @return $this
   */
  public function setId($id);

  /**
   * @return string
   */
  public function getLabel();

  /**
   * @param string $label
   *
   * @return $this
   */
  public function setLabel($label);

  /**
   * @return string
   */
  public function getKey();

  /**
   * @param string $key
   *
   * @return EasyEmailType
   */
  public function setKey($key);

  /**
   * @return array
   */
  public function getRecipient();

  /**
   * @param array $recipient
   *
   * @return $this
   */
  public function setRecipient($recipient);

  /**
   * @return array
   */
  public function getCc();

  /**
   * @param array $cc
   *
   * @return $this
   */
  public function setCc($cc);

  /**
   * @return array
   */
  public function getBcc();

  /**
   * @param array $bcc
   *
   * @return $this
   */
  public function setBcc($bcc);

  /**
   * @return string
   */
  public function getFromName();

  /**
   * @param string $fromName
   *
   * @return $this
   */
  public function setFromName($fromName);

  /**
   * @return string
   */
  public function getFromAddress();

  /**
   * @param string $fromAddress
   *
   * @return $this
   */
  public function setFromAddress($fromAddress);

  /**
   * @return string
   */
  public function getReplyToAddress();

  /**
   * @param string $replyToAddress
   *
   * @return $this
   */
  public function setReplyToAddress($replyToAddress);

  /**
   * @return string
   */
  public function getSubject();

  /**
   * @param string $subject
   *
   * @return $this
   */
  public function setSubject($subject);

  /**
   * @return string
   */
  public function getInboxPreview();

  /**
   * @param string $inboxPreview
   *
   * @return $this
   */
  public function setInboxPreview($inboxPreview);

  /**
   * @return array
   */
  public function getBodyHtml();

  /**
   * @param array $bodyHtml
   *
   * @return $this
   */
  public function setBodyHtml($bodyHtml);

  /**
   * @return string
   */
  public function getBodyPlain();

  /**
   * @param string $bodyPlain
   *
   * @return $this
   */
  public function setBodyPlain($bodyPlain);

  /**
   * @return bool
   */
  public function getGenerateBodyPlain();

  /**
   * @param bool $generateBodyPlain
   *
   * @return EasyEmailType
   */
  public function setGenerateBodyPlain($generateBodyPlain);

  /**
   * @return array
   */
  public function getAttachment();

  /**
   * @param array $attachment
   *
   * @return $this
   */
  public function setAttachment($attachment);

  /**
   * @return bool
   */
  public function getSaveAttachment();

  /**
   * @param bool $saveAttachment
   *
   * @return $this
   */
  public function setSaveAttachment($saveAttachment);

  /**
   * @return string
   */
  public function getAttachmentScheme();

  /**
   * @param string $scheme
   *
   * @return $this
   */
  public function setAttachmentScheme($scheme);

  /**
   * @return string
   */
  public function getAttachmentDirectory();

  /**
   * @param string $directory
   *
   * @return $this
   */
  public function setAttachmentDirectory($directory);

  public function setSaveEmail(bool $saveEmail): EasyEmailType;

  public function getSaveEmail(): bool;

  public function setPurgeEmails(bool $purgeEmails): EasyEmailType;

  public function setPurgePeriod(?string $purgePeriod): EasyEmailType;

  public function getPurgeInterval(): ?int;

  public function getPurgePeriod(): ?string;

  public function setPurgeInterval(?int $purgeInterval): EasyEmailType;

  public function getPurgeEmails(): bool;

  public function getAllowSavingEmail(): bool;

  public function setAllowSavingEmail($allowSavingEmail): EasyEmailType;

}
