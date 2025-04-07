<?php

namespace Drupal\klaro;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface definition for a Klaro! app config entity.
 */
interface KlaroAppInterface extends ConfigEntityInterface {

  /**
   * Getter for the id.
   *
   * @return string|null
   *   The Id.
   */
  public function id(): ?string;

  /**
   * Setter for the id.
   *
   * @param string $id
   *   The id.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setId(string $id): KlaroAppInterface;

  /**
   * Getter for the label.
   *
   * @return string|null
   *   The label.
   */
  public function label(): ?string;

  /**
   * Setter for the label.
   *
   * @param string $label
   *   The label.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setLabel(string $label): KlaroAppInterface;

  /**
   * Getter for the description.
   *
   * @return string
   *   The Klaro! app description.
   */
  public function description(): string;

  /**
   * Setter for the description.
   *
   * @param string $description
   *   The description.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setDescription(string $description = ''): KlaroAppInterface;

  /**
   * Getter for if is default.
   *
   * @return bool
   *   If is default.
   */
  public function isDefault(): bool;

  /**
   * Setter for if is default.
   *
   * @param bool $default
   *   If is default.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setDefault(bool $default): KlaroAppInterface;

  /**
   * Getter for the purposes.
   *
   * @return array
   *   The purposes of the app.
   */
  public function purposes(): array;

  /**
   * Setter for the purposes.
   *
   * @param array $purposes
   *   The purposes.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setPurposes(array $purposes = []): KlaroAppInterface;

  /**
   * Getter for the cookies.
   *
   * @return array
   *   The cookies.
   */
  public function cookies(): array;

  /**
   * Setter for the cookies.
   *
   * @param array $cookies
   *   The cookies.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setCookies(array $cookies = []): KlaroAppInterface;

  /**
   * Getter for if is required.
   *
   * @return bool
   *   If is required.
   */
  public function isRequired(): bool;

  /**
   * Setter for if is required.
   *
   * @param bool $required
   *   If is required.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setRequired(bool $required): KlaroAppInterface;

  /**
   * Getter for if is opt out.
   *
   * @return bool
   *   If is opt out.
   */
  public function isOptOut(): bool;

  /**
   * Setter for if is opt out.
   *
   * @param bool $opt_out
   *   If is opt out.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setOptOut(bool $opt_out): KlaroAppInterface;

  /**
   * Getter for if is only once.
   *
   * @return bool
   *   If is only once.
   */
  public function isOnlyOnce(): bool;

  /**
   * Setter for if only once.
   *
   * @param bool $only_once
   *   If only once.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setOnlyOnce(bool $only_once): KlaroAppInterface;

  /**
   * Getter for if is contextual consent only.
   *
   * @return bool
   *   If is contextual consent only.
   */
  public function isContextualConsentOnly(): bool;

  /**
   * Setter for contextual consent only.
   *
   * @param bool $contextual_consent_only
   *   If contextual consent only.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setContextualConsentOnly(bool $contextual_consent_only): KlaroAppInterface;

  /**
   * Getter for contextual consent text.
   *
   * @return string
   *   Contextual consent text.
   */
  public function contextualConsentText(): string;

  /**
   * Setter for contextual consent text.
   *
   * @param string $contextual_consent_text
   *   Contextual consent text.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setContextualConsentText(string $contextual_consent_text): KlaroAppInterface;

  /**
   * Getter for the info url.
   *
   * @return string|null
   *   The url as string.
   */
  public function infoUrl(): ?string;

  /**
   * Setter for the info url.
   *
   * @param string $url
   *   The info url.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setInfoUrl(string $url = ''): KlaroAppInterface;

  /**
   * Getter for the privacy policy url.
   *
   * @return string|null
   *   The url as string.
   */
  public function privacyPolicyUrl(): ?string;

  /**
   * Setter for the privacy policy url.
   *
   * @param string $url
   *   The privacy policy url.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setPrivacyPolicyUrl(string $url = ''): KlaroAppInterface;

  /**
   * Getter for callbackCode.
   *
   * @return string
   *   The callback code.
   */
  public function callbackCode(): string;

  /**
   * Setter for callbackCode.
   *
   * @param string $callbackCode
   *   The callback code.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setCallbackCode(string $callbackCode = ''): KlaroAppInterface;

  /**
   * Getter for javascripts.
   *
   * @return array
   *   The javascripts.
   */
  public function javascripts(): array;

  /**
   * Setter for javascripts.
   *
   * @param array $javascripts
   *   The javascripts.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setJavaScripts(array $javascripts = []): KlaroAppInterface;

  /**
   * Getter for attachments.
   *
   * @return array
   *   The attachments.
   */
  public function attachments(): array;

  /**
   * Setter for attachments.
   *
   * @param array $attachments
   *   The attachments.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setAttachments(array $attachments = []): KlaroAppInterface;

  /**
   * Getter for the weight.
   *
   * @return int
   *   The weight.
   */
  public function weight(): int;

  /**
   * Setter for the weight.
   *
   * @param int $weight
   *   The weight.
   *
   * @return \Drupal\klaro\KlaroAppInterface
   *   The instance.
   */
  public function setWeight(int $weight = 0): KlaroAppInterface;

}
