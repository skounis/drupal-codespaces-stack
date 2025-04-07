<?php

namespace Drupal\klaro\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\klaro\KlaroAppInterface;

/**
 * Defines the Klaro! app config entity.
 *
 * @ingroup klaro
 *
 * @ConfigEntityType(
 *   id = "klaro_app",
 *   label = @Translation("Klaro! Service"),
 *   label_singular = @Translation("Klaro! service"),
 *   label_plural = @Translation("Klaro! services"),
 *   handlers = {
 *     "list_builder" = "Drupal\klaro\Controller\KlaroAppListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "form" = {
 *       "add" = "Drupal\klaro\Form\KlaroAppForm",
 *       "edit" = "Drupal\klaro\Form\KlaroAppForm",
 *       "delete" = "Drupal\klaro\Form\KlaroAppDeleteForm",
 *     },
 *   },
 *   links = {
 *     "collection" = "/admin/config/user-interface/klaro/services",
 *     "add-form" = "/admin/config/user-interface/klaro/services/add",
 *     "edit-form" = "/admin/config/user-interface/klaro/services/{klaro_app}",
 *     "delete-form" = "/admin/config/user-interface/klaro/services/{klaro_app}/delete"
 *   },
 *   admin_permission = "administer klaro",
 *   entity_keys = {
 *     "id" = "id",
 *     "status" = "status",
 *     "label" = "label",
 *     "description" = "description",
 *     "default" = "default",
 *     "required" = "required",
 *     "optOut" = "opt_out",
 *     "onlyOnce" = "only_once",
 *     "contextualConsentOnly" = "contextual_consent_only",
 *     "contextualConsentText" = "contextual_consent_text",
 *     "infoUrl" = "info_url",
 *     "privacyPolicyUrl" = "privacy_policy_url",
 *     "purposes" = "purposes",
 *     "cookies" = "cookies",
 *     "callbackCode" = "callback_code",
 *     "javascripts" = "javascripts",
 *     "wrapper_identifier" = "wrapper_identifier",
 *     "attachments" = "attachments",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "status",
 *     "label",
 *     "description",
 *     "default",
 *     "required",
 *     "opt_out",
 *     "only_once",
 *     "contextual_consent_only",
 *     "contextual_consent_text",
 *     "info_url",
 *     "privacy_policy_url",
 *     "purposes",
 *     "cookies",
 *     "callback_code",
 *     "javascripts",
 *     "wrapper_identifier",
 *     "attachments",
 *     "weight",
 *   },
 * )
 */
class KlaroApp extends ConfigEntityBase implements KlaroAppInterface {

  /**
   * Machine name of the app.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the app.
   *
   * @var string
   */
  protected $label;

  /**
   * The description of the app.
   *
   * This will be shown when adding or configuring this app.
   *
   * @var string
   */
  protected $description = '';

  /**
   * If the app should be enabled per default at the Klaro! consent manager.
   *
   * @var bool
   */
  protected $default = FALSE;

  /**
   * The purposes of the app.
   *
   * @var array
   */
  protected $purposes = [];

  /**
   * The cookie regex strings for deleting cookies.
   *
   * @var array
   */
  protected $cookies = [];

  /**
   * If Klaro! will not allow this app to be disabled by the user.
   *
   * @var bool
   */
  protected $required = FALSE;

  /**
   * Klaro! will load this app even before the user gave explicit consent.
   *
   * @var bool
   */
  protected $optOut = FALSE;

  /**
   * The app will only be executed once.
   *
   * Regardless how often the user toggles it on and off.
   *
   * @var bool
   */
  protected $onlyOnce = FALSE;

  /**
   * Contextual Consent Only.
   *
   * Text for contextual consent for this service.
   *
   * @var bool
   */
  protected $contextualConsentOnly = FALSE;

  /**
   * The app will only be executed once.
   *
   * Regardless how often the user toggles it on and off.
   *
   * @var bool
   */
  protected $contextualConsentText = '';

  /**
   * The url to find detailed information about the app.
   *
   * @var string
   */
  protected $infoUrl = '';

  /**
   * The url to find detailed information about the privacy policies of the app.
   *
   * @var string
   */
  protected $privacyPolicyUrl = '';

  /**
   * The callback code that will be executed after status change.
   *
   * This javascript will be executed after status change.
   * See e.g. https://matomo.org/faq/how-to/using-klaro-consent-
   * manager-with-matomo/#klaro-open-source for more information.
   *
   * @var string
   */
  protected $callback_code = '';

  /**
   * The javascripts that will added to the DOM as text/html instead.
   *
   * The values will match against a string comparison of the "src"-attributes
   * of script, iframe, img, audio and video tags.
   *
   * @var array
   */
  protected $javascripts = [];

  /**
   * The Wrapper identifiers that get a contextual blocking wrap.
   *
   * Css identifiers that will be wrapped in a contextual blocking element.
   *
   * @var array
   */
  protected $wrapper_identifier = [];

  /**
   * The attachment identifiers that will get manipulated.
   *
   * The values will exactly match the hook_attachment keys.
   *
   * @var array
   */
  protected $attachments = [];

  /**
   * The weight of the app.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * {@inheritdoc}
   */
  public function id(): ?string {
    return $this->get('id');
  }

  /**
   * {@inheritdoc}
   */
  public function setId(string $id): KlaroAppInterface {
    return $this->set('id', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): ?string {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label): KlaroAppInterface {
    return $this->set('label', $label);
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->get('description');
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description = ''): KlaroAppInterface {
    return $this->set('description', $description);
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault(): bool {
    return $this->get('default');
  }

  /**
   * {@inheritdoc}
   */
  public function setDefault(bool $default): KlaroAppInterface {
    return $this->set('default', $default);
  }

  /**
   * {@inheritdoc}
   */
  public function purposes(): array {
    return $this->get('purposes');
  }

  /**
   * {@inheritdoc}
   */
  public function setPurposes(array $purposes = []): KlaroAppInterface {
    return $this->set('purposes', $purposes);
  }

  /**
   * {@inheritdoc}
   */
  public function attachments(): array {
    return $this->get('attachments');
  }

  /**
   * {@inheritdoc}
   */
  public function setAttachments(array $attachments = []): KlaroAppInterface {
    return $this->set('attachments', $attachments);
  }

  /**
   * {@inheritdoc}
   */
  public function callbackCode(): string {
    return $this->get('callback_code');
  }

  /**
   * {@inheritdoc}
   */
  public function setCallbackCode(string $callbackCode = ''): KlaroAppInterface {
    return $this->set('callback_code', $callbackCode);
  }

  /**
   * {@inheritdoc}
   */
  public function javascripts(): array {
    return $this->get('javascripts');
  }

  /**
   * {@inheritdoc}
   */
  public function setJavaScripts(array $javascripts = []): KlaroAppInterface {
    return $this->set('javascripts', $javascripts);
  }

  /**
   * {@inheritdoc}
   */
  public function wrapperIdentifier(): array {
    return $this->get('wrapper_identifier') == '' ? [] : (array) $this->get('wrapper_identifier');
  }

  /**
   * {@inheritdoc}
   */
  public function setWrapperIdentifier(array $wrapper_identifier = []): KlaroAppInterface {
    return $this->set('wrapper_identifier', $wrapper_identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function cookies(): array {
    return $this->get('cookies');
  }

  /**
   * {@inheritdoc}
   */
  public function setCookies(array $cookies = []): KlaroAppInterface {
    return $this->set('cookies', $cookies);
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return $this->get('required');
  }

  /**
   * {@inheritdoc}
   */
  public function setRequired(bool $required): KlaroAppInterface {
    return $this->set('required', $required);
  }

  /**
   * {@inheritdoc}
   */
  public function isOptOut(): bool {
    return $this->get('opt_out') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptOut(bool $opt_out): KlaroAppInterface {
    return $this->set('opt_out', $opt_out);
  }

  /**
   * {@inheritdoc}
   */
  public function isOnlyOnce(): bool {
    return $this->get('only_once') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setOnlyOnce(bool $only_once): KlaroAppInterface {
    return $this->set('only_once', $only_once);
  }

  /**
   * {@inheritdoc}
   */
  public function isContextualConsentOnly(): bool {
    return $this->get('contextual_consent_only') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setContextualConsentOnly(bool $contextual_consent_only): KlaroAppInterface {
    return $this->set('contextual_consent_only', $contextual_consent_only);
  }

  /**
   * {@inheritdoc}
   */
  public function contextualConsentText(): string {
    return $this->get('contextual_consent_text') ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function setContextualConsentText(string $contextual_consent_text): KlaroAppInterface {
    return $this->set('contextual_consent_text', $contextual_consent_text);
  }

  /**
   * {@inheritdoc}
   */
  public function infoUrl(): ?string {
    return $this->get('info_url');
  }

  /**
   * {@inheritdoc}
   */
  public function setInfoUrl(string $url = ''): KlaroAppInterface {
    return $this->set('info_url', $url);
  }

  /**
   * {@inheritdoc}
   */
  public function privacyPolicyUrl(): ?string {
    return $this->get('privacy_policy_url');
  }

  /**
   * {@inheritdoc}
   */
  public function setPrivacyPolicyUrl(string $url = ''): KlaroAppInterface {
    return $this->set('privacy_policy_url', $url);
  }

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return $this->get('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight = 0): KlaroAppInterface {
    return $this->set('weight', $weight);
  }

}
