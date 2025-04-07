<?php

namespace Drupal\easy_email\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface for defining Email entities.
 *
 * @ingroup easy_email
 */
interface EasyEmailInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface {

  /**
   * Returns the entity creator's user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The creator user entity.
   */
  public function getCreator();

  /**
   * Sets the entity creator's user entity.
   *
   * @param \Drupal\user\UserInterface $account
   *   The creator user entity.
   *
   * @return $this
   */
  public function setCreator(UserInterface $account);

  /**
   * Returns the entity creator's user ID.
   *
   * @return int|null
   *   The creator user ID, or NULL in case the creator ID field has not been set on
   *   the entity.
   */
  public function getCreatorId();

  /**
   * Sets the entity creator's user ID.
   *
   * @param int $uid
   *   The creator user id.
   *
   * @return $this
   */
  public function setCreatorId($uid);

  /**
   * Returns the entity recipients' user entities.
   *
   * @return \Drupal\user\UserInterface[]
   *   The recipient user entities.
   */
  public function getRecipients();

  /**
   * Sets the entity recipients' user entities.
   *
   * @param \Drupal\user\UserInterface[] $accounts
   *   The recipient user entities.
   *
   * @return $this
   */
  public function setRecipients($accounts);

  /**
   * Returns the entity recipient's user ID.
   *
   * @return int[]|null
   *   The recipient user IDs, or NULL in case the recipient IDs field has not been set on
   *   the entity.
   */
  public function getRecipientIds();

  /**
   * Sets the entity recipients' user IDs.
   *
   * @param int[] $uids
   *   The recipient user ids.
   *
   * @return $this
   */
  public function setRecipientIds($uids);

  /**
   * Add a recipient by User ID
   *
   * @param int $uid
   *
   * @return $this
   */
  public function addRecipient($uid);

  /**
   * Remove a recipient by User ID
   *
   * @param int $uid
   *
   * @return $this
   */
  public function removeRecipient($uid);

  /**
   * Returns the entity's email addresses.
   *
   * @return string[]
   *   The recipient email addresses.
   */
  public function getRecipientAddresses();

  /**
   * Sets the entity's email addresses.
   *
   * @param string[] $addresses
   *   The entity's recipient email addresses.
   *
   * @return $this
   */
  public function setRecipientAddresses($addresses);

  /**
   * Add a recipient email address to the entity.
   *
   * @param string $address
   *
   * @return $this
   */
  public function addRecipientAddress($address);

  /**
   * Remove a recipient email address from the entity.
   *
   * @param string $address
   *
   * @return $this
   */
  public function removeRecipientAddress($address);

  /**
   * Returns the entity CC user entities.
   *
   * @return \Drupal\user\UserInterface[]
   *   The CC user entities.
   */
  public function getCC();

  /**
   * Sets the entity CC user entities.
   *
   * @param \Drupal\user\UserInterface[] $accounts
   *   The CC user entities.
   *
   * @return $this
   */
  public function setCC($accounts);

  /**
   * Returns the entity CC user ID.
   *
   * @return int[]|null
   *   The CC user IDs, or NULL in case the CC IDs field has not been set on
   *   the entity.
   */
  public function getCCIds();

  /**
   * Sets the entity CC user IDs.
   *
   * @param int[] $uids
   *   The CC user ids.
   *
   * @return $this
   */
  public function setCCIds($uids);

  /**
   * Add a CC by User ID
   *
   * @param int $uid
   *
   * @return $this
   */
  public function addCC($uid);

  /**
   * Remove a CC by User ID
   *
   * @param int $uid
   *
   * @return $this
   */
  public function removeCC($uid);

  /**
   * Returns the entity's CC email addresses.
   *
   * @return string[]
   *   The email addresses.
   */
  public function getCCAddresses();

  /**
   * Sets the entity's CC email addresses.
   *
   * @param string[] $addresses
   *   The entity's CC email addresses.
   *
   * @return $this
   */
  public function setCCAddresses($addresses);

  /**
   * Add a CC email address to the entity.
   *
   * @param string $address
   *
   * @return $this
   */
  public function addCCAddress($address);

  /**
   * Remove a CC email address from the entity.
   *
   * @param string $address
   *
   * @return $this
   */
  public function removeCCAddress($address);

  /**
   * Returns the entity BCC user entities.
   *
   * @return \Drupal\user\UserInterface[]
   *   The BCC user entities.
   */
  public function getBCC();

  /**
   * Sets the entity BCC user entities.
   *
   * @param \Drupal\user\UserInterface[] $accounts
   *   The BCC user entities.
   *
   * @return $this
   */
  public function setBCC($accounts);

  /**
   * Returns the entity BCC user ID.
   *
   * @return int[]|null
   *   The BCC user IDs, or NULL in case the BCC IDs field has not been set on
   *   the entity.
   */
  public function getBCCIds();

  /**
   * Sets the entity BCC user IDs.
   *
   * @param int[] $uids
   *   The BCC user ids.
   *
   * @return $this
   */
  public function setBCCIds($uids);

  /**
   * Add a BCC by User ID
   *
   * @param int $uid
   *
   * @return $this
   */
  public function addBCC($uid);

  /**
   * Remove a BCC by User ID
   *
   * @param int $uid
   *
   * @return $this
   */
  public function removeBCC($uid);

  /**
   * Returns the entity's BCC email addresses.
   *
   * @return string[]
   *   The BCC email addresses.
   */
  public function getBCCAddresses();

  /**
   * Sets the entity's BCC email addresses.
   *
   * @param string[] $addresses
   *   The entity's BCC email addresses.
   *
   * @return $this
   */
  public function setBCCAddresses($addresses);

  /**
   * Add a BCC email address to the entity.
   *
   * @param string $address
   *
   * @return $this
   */
  public function addBCCAddress($address);

  /**
   * Remove a BCC email address from the entity.
   *
   * @param string $address
   *
   * @return $this
   */
  public function removeBCCAddress($address);

  /**
   * Returns the Subject text
   *
   * @return string|null
   */
  public function getSubject();

  /**
   * Sets the Subject
   *
   * @param string $subject
   *
   * @return $this
   */
  public function setSubject($subject);

  /**
   * Returns the From name
   *
   * @return string|null
   */
  public function getFromName();

  /**
   * Sets the From name
   *
   * @param string $from_name
   *
   * @return $this
   */
  public function setFromName($from_name);

  /**
   * Returns the From email address
   *
   * @return string|null
   */
  public function getFromAddress();

  /**
   * Sets the From email address
   *
   * @param string $from_email
   *
   * @return $this
   */
  public function setFromAddress($from_email);

  /**
   * Returns the Reply To email address
   *
   * @return string|null
   */
  public function getReplyToAddress();

  /**
   * Sets the Reply To email address
   *
   * @param string $reply_to_email
   *
   * @return $this
   */
  public function setReplyToAddress($reply_to_email);

  /**
   * Returns the HTML body render array
   *
   * @return array|null
   */
  public function getHtmlBody();

  /**
   * Sets the HTML body text and text format
   *
   * @param string $text
   *   The HTML text
   * @param string $format
   *   The text format to render in
   *
   * @return $this
   */
  public function setHtmlBody($text, $format);

  /**
   * Returns the Plain Text body render array
   *
   * @return string|null
   */
  public function getPlainBody();

  /**
   * Sets the Plain Text body
   *
   * @param string $text
   *
   * @return $this
   */
  public function setPlainBody($text);

  /**
   * Returns the Inbox Preview render array
   *
   * @return array|null
   */
  public function getInboxPreview();

  /**
   * Sets the Inbox Preview
   *
   * @param string $text
   *
   * @return $this
   */
  public function setInboxPreview($text);

  /**
   * Returns the entity file attachment entities.
   *
   * @return \Drupal\file\FileInterface[]
   *   The file attachment entities.
   */
  public function getAttachments();

  /**
   * Sets the entity file attachment entities.
   *
   * @param \Drupal\file\FileInterface $files
   *   The file attachment entities.
   *
   * @return $this
   */
  public function setAttachments($files);

  /**
   * Returns the entity file attachments IDs.
   *
   * @return int[]|null
   *   The file attachment IDs, or NULL in case the file attachment IDs field has not been set on
   *   the entity.
   */
  public function getAttachmentIds();

  /**
   * Sets the entity file attachment IDs.
   *
   * @param int[] $fids
   *   The file attachment ids.
   *
   * @return $this
   */
  public function setAttachmentIds($fids);

  /**
   * Add file attachment by ID.
   *
   * @param int $fid
   *   The file attachment id.
   *
   * @return $this
   */
  public function addAttachment($fid);

  /**
   * Remove file attachment by ID.
   *
   * @param int $fid
   *   The file attachment id.
   *
   * @return $this
   */
  public function removeAttachment($fid);

  /**
   * Returns the entity's dynamic attachment paths.
   *
   * @return string[]
   *   The entity's dynamic attachment paths.
   */
  public function getAttachmentPaths();

  /**
   * Sets the entity's dynamic attachment paths.
   *
   * @param string[] $paths
   *   The entity's dynamic attachment paths.
   *
   * @return $this
   */
  public function setAttachmentPaths($paths);

  /**
   * Add a dynamic attachment path to the entity.
   *
   * @param string $path
   *
   * @return $this
   */
  public function addAttachmentPath($path);

  /**
   * Remove a dynamic attachment path from the entity.
   *
   * @param string $path
   *
   * @return $this
   */
  public function removeAttachmentPath($path);

  /**
   * @return array
   */
  public function getEvaluatedAttachments();

  /**
   * @param array $attachments
   *
   * @return $this;
   */
  public function setEvaluatedAttachments($attachments);

  /**
   * @param stdClass $attachment
   *
   * @return $this
   */
  public function addEvaluatedAttachment($attachment);

  /**
   * @param stdClass $attachment
   *
   * @return $this
   */
  public function removeEvaluatedAttachment($attachment);

  /**
   * Gets the Email creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Email.
   */
  public function getCreatedTime();

  /**
   * Sets the Email creation timestamp.
   *
   * @param int $timestamp
   *   The Email creation timestamp.
   *
   * @return \Drupal\easy_email\Entity\EasyEmailInterface
   *   The called Email entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Email sent status.
   *
   * @return bool
   *   TRUE if the Email is sent.
   */
  public function isSent();

  /**
   * Returns the Email sent timestamp.
   *
   * @return int
   *   The integer timestamp of the sent time for the email
   */
  public function getSentTime();


  /**
   * Sets the sent timestamp of a Email.
   *
   * @param int $timestamp
   *   The integer timestamp of the sent time for the email
   *
   * @return \Drupal\easy_email\Entity\EasyEmailInterface
   *   The called Email entity.
   */
  public function setSentTime($timestamp);

  /**
   * Gets the Email revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Email revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\easy_email\Entity\EasyEmailInterface
   *   The called Email entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Email revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Email revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\easy_email\Entity\EasyEmailInterface
   *   The called Email entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Returns the Email key
   *
   * @return string|null
   */
  public function getKey();

  /**
   * Sets the Email key
   *
   * @param string $key
   *
   * @return $this
   */
  public function setKey($key);

  public function getCombinedCCAddresses();

  public function getCombinedBCCAddresses();

  public function getCombinedRecipientAddresses();

}
