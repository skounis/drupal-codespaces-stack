<?php

namespace Drupal\easy_email\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Email template entity.
 *
 * @ConfigEntityType(
 *   id = "easy_email_type",
 *   label = @Translation("Email template"),
 *   label_collection = @Translation("Email templates"),
 *   label_singular = @Translation("email template"),
 *   label_plural = @Translation("email templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count email template",
 *     plural = "@count email templates",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\easy_email\EasyEmailTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\easy_email\Form\EasyEmailTypeForm",
 *       "edit" = "Drupal\easy_email\Form\EasyEmailTypeForm",
 *       "delete" = "Drupal\easy_email\Form\EasyEmailTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\easy_email\EasyEmailTypeHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\easy_email\EasyEmailTypeAccessControlHandler",
 *   },
 *   config_prefix = "easy_email_type",
 *   admin_permission = "administer email types",
 *   bundle_of = "easy_email",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "label",
 *     "id",
 *     "key",
 *     "recipient",
 *     "cc",
 *     "bcc",
 *     "fromName",
 *     "fromAddress",
 *     "replyToAddress",
 *     "subject",
 *     "inboxPreview",
 *     "bodyHtml",
 *     "bodyPlain",
 *     "generateBodyPlain",
 *     "attachment",
 *     "saveAttachment",
 *     "attachmentScheme",
 *     "attachmentDirectory",
 *     "saveEmail",
 *     "allowSavingEmail",
 *     "purgeEmails",
 *     "purgeInterval",
 *     "purgePeriod",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/email-templates/templates/{easy_email_type}",
 *     "add-form" = "/admin/structure/email-templates/templates/add",
 *     "edit-form" = "/admin/structure/email-templates/templates/{easy_email_type}/edit",
 *     "delete-form" = "/admin/structure/email-templates/templates/{easy_email_type}/delete",
 *     "collection" = "/admin/structure/email-templates/templates"
 *   }
 * )
 */
class EasyEmailType extends ConfigEntityBundleBase implements EasyEmailTypeInterface {

  /**
   * The Email type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Email type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Email type key
   *
   * @var string
   */
  protected $key;

  /**
   * The Email type recipients
   *
   * @var array
   */
  protected $recipient;

  /**
   * The Email type CC recipients
   *
   * @var array
   */
  protected $cc;

  /**
   * The Email type BCC recipients
   *
   * @var array
   */
  protected $bcc;

  /**
   * The Email type From name.
   *
   * @var string
   */
  protected $fromName;

  /**
   * The Email type From address.
   *
   * @var string
   */
  protected $fromAddress;

  /**
   * The Email type Reply To address.
   *
   * @var string
   */
  protected $replyToAddress;

  /**
   * The Email type Subject.
   *
   * @var string
   */
  protected $subject;

  /**
   * The Email type Inbox Preview.
   *
   * @var string
   */
  protected $inboxPreview;

  /**
   * The Email type Body HTML text.
   *
   * @var array
   */
  protected $bodyHtml;

  /**
   * The Email type Body plain text.
   *
   * @var string
   */
  protected $bodyPlain;

  /**
   * Whether or not to automatically generate the Body plain text from the HTML version
   * @var bool
   */
  protected $generateBodyPlain;

  /**
   * The Email type attachments.
   *
   * @var array
   */
  protected $attachment;

  /**
   * Whether or not to save dynamic attachments to the email entity.
   *
   * @var bool
   */
  protected $saveAttachment;

  /**
   * @var string
   */
  protected $attachmentScheme;

  /**
   * @var string
   */
  protected $attachmentDirectory;

  /**
   * @var bool
   */
  protected bool $saveEmail;

  /**
   * @var bool
   */
  protected bool $allowSavingEmail;

  /**
   * @var bool
   */
  protected bool $purgeEmails;

  /**
   * @var int|null
   */
  protected ?int $purgeInterval;

  /**
   * @var string|null
   */
  protected ?string $purgePeriod;

  public function __construct(array $values, $entity_type) {
    $values += [
      'recipient' => [],
      'cc' => [],
      'bcc' => [],
      'bodyHtml' => [],
      'attachment' => [],
      'saveEmail' => TRUE,
      'allowSavingEmail' => TRUE,
      'purgeEmails' => FALSE,
      'purgeInterval' => NULL,
      'purgePeriod' => 'days',
    ];
    parent::__construct($values, $entity_type);
  }

  /**
   * @return string
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @param string $id
   *
   * @return $this
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * @return string
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * @param string $label
   *
   * @return $this
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * @return string
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * @param string $key
   *
   * @return EasyEmailType
   */
  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  /**
   * @return array
   */
  public function getRecipient() {
    return $this->recipient;
  }

  /**
   * @param array $recipient
   *
   * @return $this
   */
  public function setRecipient($recipient) {
    $this->recipient = $recipient;
    return $this;
  }

  /**
   * @return array
   */
  public function getCc() {
    return $this->cc;
  }

  /**
   * @param array $cc
   *
   * @return $this
   */
  public function setCc($cc) {
    $this->cc = $cc;
    return $this;
  }

  /**
   * @return array
   */
  public function getBcc() {
    return $this->bcc;
  }

  /**
   * @param array $bcc
   *
   * @return $this
   */
  public function setBcc($bcc) {
    $this->bcc = $bcc;
    return $this;
  }

  /**
   * @return string
   */
  public function getFromName() {
    return $this->fromName;
  }

  /**
   * @param string $fromName
   *
   * @return $this
   */
  public function setFromName($fromName) {
    $this->fromName = $fromName;
    return $this;
  }

  /**
   * @return string
   */
  public function getFromAddress() {
    return $this->fromAddress;
  }

  /**
   * @param string $fromAddress
   *
   * @return $this
   */
  public function setFromAddress($fromAddress) {
    $this->fromAddress = $fromAddress;
    return $this;
  }

  /**
   * @return string
   */
  public function getReplyToAddress() {
    return $this->replyToAddress;
  }

  /**
   * @param string $replyToAddress
   *
   * @return $this
   */
  public function setReplyToAddress($replyToAddress) {
    $this->replyToAddress = $replyToAddress;
    return $this;
  }

  /**
   * @return string
   */
  public function getSubject() {
    return $this->subject;
  }

  /**
   * @param string $subject
   *
   * @return $this
   */
  public function setSubject($subject) {
    $this->subject = $subject;
    return $this;
  }

  /**
   * @return string
   */
  public function getInboxPreview() {
    return $this->inboxPreview;
  }

  /**
   * @param string $inboxPreview
   *
   * @return $this
   */
  public function setInboxPreview($inboxPreview) {
    $this->inboxPreview = $inboxPreview;
    return $this;
  }

  /**
   * @return array
   */
  public function getBodyHtml() {
    return $this->bodyHtml;
  }

  /**
   * @param array $bodyHtml
   *
   * @return $this
   */
  public function setBodyHtml($bodyHtml) {
    $this->bodyHtml = $bodyHtml;
    return $this;
  }

  /**
   * @return string
   */
  public function getBodyPlain() {
    return $this->bodyPlain;
  }

  /**
   * @param string $bodyPlain
   *
   * @return $this
   */
  public function setBodyPlain($bodyPlain) {
    $this->bodyPlain = $bodyPlain;
    return $this;
  }

  /**
   * @return bool
   */
  public function getGenerateBodyPlain() {
    return (bool) $this->generateBodyPlain;
  }

  /**
   * @param bool $generateBodyPlain
   *
   * @return EasyEmailType
   */
  public function setGenerateBodyPlain($generateBodyPlain) {
    $this->generateBodyPlain = $generateBodyPlain;
    return $this;
  }

  /**
   * @return array
   */
  public function getAttachment() {
    return $this->attachment;
  }

  /**
   * @param array $attachment
   *
   * @return $this
   */
  public function setAttachment($attachment) {
    $this->attachment = $attachment;
    return $this;
  }

  /**
   * @return bool
   */
  public function getSaveAttachment() {
    return (bool) $this->saveAttachment;
  }

  /**
   * @param bool $saveAttachment
   *
   * @return $this
   */
  public function setSaveAttachment($saveAttachment) {
    $this->saveAttachment = $saveAttachment;
    return $this;
  }

  /**
   * @return string
   */
  public function getAttachmentScheme() {
    return $this->attachmentScheme;
  }

  /**
   * @param string $attachmentScheme
   *
   * @return EasyEmailType
   */
  public function setAttachmentScheme($attachmentScheme) {
    $this->attachmentScheme = $attachmentScheme;
    return $this;
  }

  /**
   * @return string
   */
  public function getAttachmentDirectory() {
    return $this->attachmentDirectory;
  }

  /**
   * @param string $attachmentDirectory
   *
   * @return EasyEmailType
   */
  public function setAttachmentDirectory($attachmentDirectory) {
    $this->attachmentDirectory = $attachmentDirectory;
    return $this;
  }

  public function getSaveEmail(): bool {
    return $this->saveEmail;
  }

  public function setSaveEmail($saveEmail): EasyEmailType {
    $this->saveEmail = (bool) $saveEmail;
    return $this;
  }

  public function getPurgeEmails(): bool {
    return $this->purgeEmails;
  }

  public function setPurgeEmails($purgeEmails): EasyEmailType {
    $this->purgeEmails = (bool) $purgeEmails;
    return $this;
  }

  public function getPurgeInterval(): ?int {
    return $this->purgeInterval;
  }

  public function setPurgeInterval($purgeInterval): EasyEmailType {
    if ($purgeInterval !== NULL) {
      $purgeInterval = (int) $purgeInterval;
    }
    $this->purgeInterval = $purgeInterval;
    return $this;
  }

  public function getPurgePeriod(): ?string {
    return $this->purgePeriod;
  }

  public function setPurgePeriod($purgePeriod): EasyEmailType {
    $this->purgePeriod = $purgePeriod;
    return $this;
  }

  public function getAllowSavingEmail(): bool {
    return $this->allowSavingEmail;
  }

  public function setAllowSavingEmail($allowSavingEmail): EasyEmailType {
    $this->allowSavingEmail = (bool) $allowSavingEmail;
    return $this;
  }



}
