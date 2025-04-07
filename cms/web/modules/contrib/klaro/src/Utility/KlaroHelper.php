<?php

namespace Drupal\klaro\Utility;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Asset\LibrariesDirectoryFileFinder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManager;
use Drupal\Core\Url;
use Drupal\klaro\Entity\KlaroApp;
use Drupal\klaro\KlaroAppInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides helper methods for klaro.
 */
class KlaroHelper {

  use StringTranslationTrait;

  /**
   * The context key for Klaro! specific translatable strings.
   *
   * @var string
   */
  const TRANSLATION_CONTEXT = 'klaro';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The libraries directory finder.
   *
   * @var \Drupal\Core\Logger\LibrariesDirectoryFileFinder
   */
  protected $librariesFinder;

  /**
   * The file_url_generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The theme.manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * Constructs a KlaroHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languages
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Logger\LibrariesDirectoryFileFinder $libraries_finder
   *   The libraries_finder service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file_url_generator service.
   * @param \Drupal\Core\Theme\ThemeManager $theme_manager
   *   Theme theme.manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $languages,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger,
    LibrariesDirectoryFileFinder $libraries_finder,
    FileUrlGeneratorInterface $file_url_generator,
    ThemeManager $theme_manager,
  ) {
    $this->configFactory = $config_factory;
    $this->languageManager = $languages;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->renderer = $renderer;
    $this->logger = $logger;
    $this->librariesFinder = $libraries_finder;
    $this->fileUrlGenerator = $file_url_generator;
    $this->themeManager = $theme_manager;
  }

  /**
   * Gets all available purposes.
   *
   * @return array
   *   The purposes.
   */
  public function getAvailablePurposes(): array {
    $purpose_storage = $this->entityTypeManager->getStorage('klaro_purpose');

    $query = $purpose_storage->getQuery();
    $query->sort('weight');

    $result = $query->accessCheck(FALSE)->execute();

    return $purpose_storage->loadMultiple($result);
  }

  /**
   * Returns if all necessary dependencies are satisfied to use klaro.
   *
   * If app_ids are provided an additional check is performed if specified apps
   * are enabled. Will return FALSE if one of the apps are not enabled.
   *
   * @param string|string[] $app_ids
   *   The app id(s) to check.
   *
   * @return bool
   *   If has access in general or to a specific app.
   */
  public function hasAccess($app_ids = []): bool {
    if (!$this->currentUser->hasPermission('use klaro')) {
      return FALSE;
    }
    $enabled_apps = $this->getApps();
    if (!$enabled_apps || array_diff((array) $app_ids, array_keys($enabled_apps))) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Returns if at least one app is enabled that requires user consent.
   *
   * @return bool
   *   TRUE, if at least one enabled app requires consent.
   */
  public function consentManagementRequired(): bool {
    return (bool) $this->getApps(TRUE, TRUE);
  }

  /**
   * Gets klaro.settings config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The klaro settings.
   */
  public function getSettings(): ImmutableConfig {
    return $this->configFactory->get('klaro.settings');
  }

  /**
   * Gets klaro.texts config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The klaro settings.
   */
  public function getTexts(): ImmutableConfig {
    return $this->configFactory->get('klaro.texts');
  }

  /**
   * Gets klaro.texts config.
   *
   * @return bool
   *   Return TRUE if library is found else FALSE.
   */
  public function hasLibraryFiles(): bool {
    return $this->librariesFinder->find('klaro') ? TRUE : FALSE;
  }

  /**
   * Gets Path for deprecated library.
   *
   * @return bool
   *   Return TRUE if deprecated library is found else FALSE.
   */
  public function hasDeprecatedLibraryFiles(): bool {
    return $this->librariesFinder->find('klaro-js') ? TRUE : FALSE;
  }

  /**
   * Gets drupal render service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The render service.
   */
  public function getRenderer(): RendererInterface {
    return $this->renderer;
  }

  /**
   * Get an array that should be used for drupalSettings.
   *
   * @return array
   *   The drupalSettings array.
   */
  public function processDrupalSettings(): array {
    $config = $this->configFactory->get('klaro.settings');
    $config_texts = $this->configFactory->get('klaro.texts');
    $settings = [];
    $settings['config'] = static::snakeToCamel($config->get('library'));

    // Set dialog mode.
    $dialog_mode = $config->get('dialog_mode');
    if ($dialog_mode == 'manager') {
      $settings['config']['mustConsent'] = TRUE;
    }
    elseif ($dialog_mode == 'notice_modal') {
      $settings['config']['noticeAsModal'] = TRUE;
    }
    $settings['dialog_mode'] = $dialog_mode;

    $uri = $config_texts->get('consentModal.privacyPolicy.url');
    $settings['config']['privacyPolicy'] = $uri ? Url::fromUri($uri)->toString() : NULL;

    $cookie_domains = $config->get('deletable_cookie_domains');
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    // Get only the langcode part.
    $langcode = explode('-', $langcode);
    $langcode = reset($langcode);

    foreach ($this->getApps() as $app) {
      // Add app cookies to the config.
      $cookies = array_map('array_values', $app->cookies());
      foreach ($app->cookies() as $info) {
        // Also manage cookies from deletable domains.
        foreach ($cookie_domains as $domain) {
          $cookies[] = [$info['regex'], $info['path'], $domain];
        }
      }
      $settings['config']['services'][] = [
        'name' => $app->id(),
        'default' => $app->isDefault(),
        'title' => $app->label(),
        'description' => $config->get('process_descriptions') ? $this->processDescription($app) : $app->description(),
        'purposes' => $app->purposes(),
        'callbackCode' => $app->callbackCode(),
        'cookies' => $cookies,
        'required' => $app->isRequired(),
        'optOut' => $app->isOptOut(),
        'onlyOnce' => $app->isOnlyOnce(),
        'contextualConsentOnly' => $app->isContextualConsentOnly(),
        'contextualConsentText' => $this->filterXss($app->contextualConsentText()),
        'wrapperIdentifier' => $app->wrapperIdentifier(),
        'translations' => [
          "$langcode" => [
            "title" => $app->label(),
          ],
        ],
      ];
    }

    $translations = $config_texts->get();
    // Process {purposes} like klaro-lib does for initial-description.
    if (strpos($translations['consentModal']['description'], "{purposes}") !== FALSE) {
      $txt = $translations['consentModal']['description'];
      $purposes = $this->activePurposesString();
      if ($settings['config']['htmlTexts']) {
        $txt = str_replace("{purposes}", "<strong>" . $purposes . "</strong>", $txt);
      }
      else {
        $txt = str_replace("{purposes}", $purposes, $txt);
      }
      $translations['consentModal']['description'] = $txt;
    }
    $translations['purposeItem']['service'] = $this->t('Service', [], ['context' => 'klaro']);
    $translations['purposeItem']['services'] = $this->t('Services', [], ['context' => 'klaro']);

    unset($translations['consentModal']['privacyPolicy']['url']);
    $translations['poweredBy'] = !empty($translations['poweredBy']) ? $translations['poweredBy'] : '';
    $translations['consentNotice']['privacyPolicy']['name'] = $translations['consentModal']['privacyPolicy']['name'];
    unset($translations['_core'], $translations['langcode']);

    $settings['config']['translations'][$langcode] = $translations;
    $settings['config']['translations'][$langcode]['privacyPolicy'] = $translations['consentModal']['privacyPolicy'];
    $settings['config']['translations'][$langcode]['purposes'] = $this->optionPurposes(TRUE);

    $settings['show_toggle_button'] = $config->get('show_toggle_button');
    $settings['toggle_button_icon'] = $config->get('toggle_button_icon');
    $settings['show_close_button'] = $config->get('show_close_button');
    $settings['exclude_urls'] = $config->get('exclude_urls');
    $settings['disable_urls'] = $config->get('disable_urls');

    // Force show title. For a11y it will visually hidden later.
    $settings['config']['showNoticeTitle'] = TRUE;
    // Visually hide only if not explicitly configured to show.
    if (!$config->get('show_notice_title')) {
      $settings['config']['additionalClass'] .= " hide-consent-dialog-title";
    }

    $styles = $config->get('styles');
    if (!empty($styles)) {
      $settings['config']['styling']['theme'] = $styles;
    }
    if (isset($settings['config']['learnMoreAsButton']) && $settings['config']['learnMoreAsButton']) {
      $settings['config']['additionalClass'] .= " learn-more-as-button";
    }

    $settings['config']['additionalClass'] .= " klaro-theme-" . $this->themeManager->getActiveTheme()->getName();

    return $settings;
  }

  /**
   * Convert under_score type array keys to camelCase type array keys.
   *
   * @param array $array
   *   The array to convert.
   *
   * @return array
   *   The array with converted keys.
   */
  private static function snakeToCamel(array $array): array {
    $finalArray = [];

    foreach ($array as $key => $value) {
      if (strpos($key, "_")) {
        $key = lcfirst(str_replace("_", "", ucwords($key, "_")));
      }
      if (!is_array($value)) {
        $finalArray[$key] = $value;
      }
      else {
        $finalArray[$key] = static::snakeToCamel($value);
      }
    }

    return $finalArray;
  }

  /**
   * Get all apps.
   *
   * @param bool $only_enabled
   *   If only enabled apps should be fetched.
   * @param bool $only_not_required
   *   If only apps should be fetched that are not set to be required.
   *
   * @return \Drupal\klaro\KlaroAppInterface[]
   *   The enabled apps.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getApps(bool $only_enabled = TRUE, bool $only_not_required = FALSE): array {
    $storage = $this->entityTypeManager->getStorage('klaro_app');

    $query = $storage->getQuery();
    $query->sort('weight');
    if ($only_enabled) {
      $query->condition('status', TRUE);
    }
    if ($only_not_required) {
      $query->condition('required', FALSE);
    }
    $result = $storage->loadMultiple($query->accessCheck(FALSE)->execute());

    $settings = $this->getSettings();
    if ($settings->get('block_unknown')) {
      $unknown_app = new KlaroApp([], 'klaro_app');
      $unknown_app->setId("unknown_app");
      $unknown_app->setLabel($settings->get('block_unknown_label'));
      $unknown_app->setDescription($settings->get('block_unknown_description'));
      $unknown_app->setPurposes(['external_content']);
      $result["unknown_app"] = $unknown_app;
    }

    return $result;
  }

  /**
   * Check if on disabled uri pattern.
   *
   * @return bool
   *   True or false.
   */
  public function onDisabledUri(): bool {
    $config = $this->configFactory->get('klaro.settings');
    $disable_urls = $config->get('disable_urls');

    // Disable media/oembed as the outer iframe of remote-video will be handled.
    if ($this->request->attributes->get('_route') == 'media.oembed_iframe') {
      return TRUE;
    }

    $uri = $this->request->getRequestUri();
    $found = FALSE;
    foreach ($disable_urls as $url_pattern) {
      $pattern = '/' . $url_pattern . '/';
      if (preg_match($pattern, $uri) > 0) {
        $found = TRUE;
      }
    }
    return $found;
  }

  /**
   * Check if on excluded uri pattern.
   *
   * @return bool
   *   True or false.
   */
  public function onExcludedUri(): bool {
    $config = $this->configFactory->get('klaro.settings');
    $exclude_urls = $config->get('exclude_urls');
    if (empty($exclude_urls)) {
      return FALSE;
    }

    $uri = $this->request->getRequestUri();
    $found = FALSE;
    foreach ($exclude_urls as $url_pattern) {
      $pattern = '/' . $url_pattern . '/';
      if (preg_match($pattern, $uri) > 0) {
        $found = TRUE;
      }
    }
    return $found;
  }

  /**
   * Retrieves available purposes as an options array.
   *
   * @param bool $with_description
   *   Returns an array of titles and descriptions if enabled.
   *
   * @return array
   *   The purposes options.
   */
  public function optionPurposes($with_description = FALSE): array {
    $options = [];

    foreach ($this->getAvailablePurposes() as $purpose) {
      $options[$purpose->id()] = $with_description ? [
        'title' => $purpose->label(),
        'description' => $purpose->description(),
      ] : $purpose->label();
    }

    return $options;
  }

  /**
   * Retrieves active purposes as a string.
   *
   * @return string
   *   The purposes string.
   */
  public function activePurposesString(): string {
    $all_purposes = $this->optionPurposes();
    $all_apps = $this->getApps(TRUE);
    $collected_purposes = [];
    foreach ($all_apps as $app) {
      foreach ($app->purposes() as $purpose) {
        if (!array_key_exists($purpose, $collected_purposes)) {
          if (array_key_exists($purpose, $all_purposes)) {
            array_push($collected_purposes, $all_purposes[$purpose]);
          }
        }
      }
    }

    return implode(' & ', $collected_purposes);
  }

  /**
   * Creates the description of the app by combining several information.
   *
   * @param \Drupal\klaro\KlaroAppInterface $app
   *   The Klaro! app.
   *
   * @return string
   *   The processed description.
   */
  public function processDescription(KlaroAppInterface $app): string {
    $description = $app->description();

    $pp_url = !empty($app->privacyPolicyUrl()) ? Url::fromUri($app->privacyPolicyUrl())->toString() : "";
    $i_url = !empty($app->infoUrl()) ? Url::fromUri($app->infoUrl())->toString() : "";
    $urls = [
      'privacy_policy' => $pp_url,
      'info' => $i_url,
    ];

    if ($this->getSettings()->get('library.html_texts')) {
      // Process with html links (only works if library.html_texts is TRUE).
      foreach (array_filter($urls) as $key => $url) {
        $description .= " - ";
        switch ($key) {
          case 'privacy_policy':
            $description .= '<a href="' . $url . '" target="_blank">' . $this->t('Privacy policy', [], ['context' => 'klaro']) . '</a>';
            break;

          case 'info':
            $description .= '<a href="' . $url . '" target="_blank">' . $this->t('Info', [], ['context' => 'klaro']) . '</a>';
            break;
        }
      }
    }
    else {
      // Process as plain text.
      foreach (array_filter($urls) as $key => $url) {
        $description .= " - ";
        switch ($key) {
          case 'privacy_policy':
            $description .= $this->t('Privacy policy', [], ['context' => 'klaro']);
            break;

          case 'info':
            $description .= $this->t('Info', [], ['context' => 'klaro']);
            break;
        }
        $description .= ": {$url}";
      }
    }

    return $description;
  }

  /**
   * Modify attributes for klaro.
   *
   * @param \Drupal\Core\Template\Attribute|array $attributes
   *   The attributes to change.
   * @param string $name
   *   The name/label of the Klaro App.
   * @param string $src
   *   (optional) Use this value as default if no src-attribute is given.
   *
   * @return \Drupal\Core\Template\Attribute|array
   *   The changes attributes.
   */
  public function rewriteAttributes($attributes, $name, $src = FALSE) {
    $src = $attributes['src'] ?? $src;
    $attributes['data-src'] = $src;
    $attributes['data-name'] = $name;
    unset($attributes['src']);
    if (isset($attributes['type'])) {
      $attributes['data-type'] = $attributes['type'];
      $attributes['type'] = 'text/plain';
    }

    return $attributes;
  }

  /**
   * Try to determine thumbnail from entity.
   *
   * @param object $entity
   *   The entity to check.
   *
   * @return string|bool[]
   *   The url or false.
   */
  public function getThumbnail($entity) {
    if (!$this->getSettings()->get('get_entity_thumbnail')) {
      return FALSE;
    }
    $url = $entity?->thumbnail?->entity?->getFileUri();
    if ($url) {
      $url = $this->fileUrlGenerator->generateAbsoluteString($url);
      // Check if URL is not external.
      if (UrlHelper::isExternal($url)) {
        $external_is_local = UrlHelper::externalIsLocal($url, $this->request->getSchemeAndHttpHost());
        if ($external_is_local) {
          return $url;
        }
      }
      else {
        return $url;
      }
    }
    return FALSE;
  }

  /**
   * Matches klaro apps against a string(src-attribute).
   *
   * @param string $str
   *   The string to check.
   *
   * @return \Drupal\klaro\KlaroAppInterface|bool[]
   *   The klaro app or false.
   */
  public function matchKlaroApp(string $str) {
    $klaro_apps = $this->getApps();
    $settings = $this->getSettings();
    $found_klaro_app = FALSE;
    foreach ($klaro_apps as $klaro_app) {
      foreach ($klaro_app->javascripts() as $script_src_identifier) {
        if (mb_strpos($str, $script_src_identifier) !== FALSE) {
          $found_klaro_app = $klaro_app;
          break;
        }
      }

      if ($found_klaro_app) {
        break;
      }
    }

    // Check if there are unknown external resources.
    if (!$found_klaro_app && ($settings->get('block_unknown') || $settings->get('log_unknown_resources'))) {
      if (UrlHelper::isExternal($str)) {
        $external_is_local = UrlHelper::externalIsLocal($str, $this->request->getSchemeAndHttpHost());
        if (!$external_is_local) {
          if ($settings->get('block_unknown')) {
            $found_klaro_app = $klaro_apps['unknown_app'];
          }
          if ($settings->get('log_unknown_resources')) {
            $this->logger->get('klaro')->notice('Unknown external resource %resource requested, we recommend to create a service for this resource.',
              [
                '%resource' => $str,
              ]
            );
          }
        }
      }
    }

    return $found_klaro_app;
  }

  /**
   * Searches the html of an ajax-command for tags to decorate.
   *
   * @param array $cmd
   *   An AJAX command render array.
   * @param bool $inspect_only
   *   Only inspect (and log) and do not change markup.
   *
   * @return array
   *   The modified command.
   */
  public function handleAjaxCommand(array $cmd, bool $inspect_only = FALSE):array {

    // @todo more cases?
    switch ($cmd['command']) {
      // Support all commands of 'insert' group.
      // AfterCommand, AppendCommand, BeforeCommand, HtmlCommand
      // PrependCommand, InsertCommand and ReplaceCommand.
      case 'insert':
        $modified_data = $this->processHtml((string) $cmd['data'], $inspect_only);
        $cmd['data'] = Markup::create($modified_data);
        break;

      default:
        // code...
        break;
    }

    return $cmd;
  }

  /**
   * Searches html for tags and decorates them.
   *
   * Matches the src attribute against the klaro apps and adds the attributes
   * required for klaro to consensually block/load them.
   *
   * @param string $html
   *   The html to process.
   * @param bool $inspect_only
   *   Only inspect (and log) and do not change markup.
   *
   * @return string
   *   The processed html.
   */
  public function processHtml(string $html, bool $inspect_only = FALSE): string {

    if (!$this->hasAccess()) {
      return $html;
    }

    $klaro_apps = $this->getApps();

    $complete_html = strpos($html, '<!DOCTYPE') !== FALSE;
    // If "complete html" is supplied use DomDocument to create.
    if ($complete_html) {
      $dom = new \DOMDocument();
      $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
      $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_SCHEMA_CREATE);
    }
    else {
      // If "html fragment" is supplied use The drupal Html helper.
      $dom = Html::load($html);
    }
    if (!$dom) {
      return $html;
    }

    foreach ($dom->getElementsByTagName('video') as $video) {
      if ($video->hasAttribute('data-src') && $video->hasAttribute('data-name')) {
        continue;
      }

      $sources = [];
      if (!$video->hasAttribute('src')) {
        $sources = [];
        foreach ($video->childNodes as $n) {
          if ($n->nodeName === 'source') {
            $sources[] = $n;
          }
        }
        if (empty($sources)) {
          continue;
        }
        $initial_src = $sources[0]->getAttribute('src');
      }
      else {
        $initial_src = $video->getAttribute('src');
      }
      $found_klaro_app = $this->matchKlaroApp($initial_src);

      if (!$inspect_only && $found_klaro_app) {
        if (!$video->hasAttribute('src')) {
          foreach ($sources as $source) {
            $source->setAttribute('data-src', $initial_src);
            $source->removeAttribute('src');
            $source->setAttribute('data-name', $found_klaro_app->id());
          }
        }
        else {
          $video->setAttribute('data-src', $initial_src);
          $video->removeAttribute('src');
          $video->setAttribute('data-name', $found_klaro_app->id());
        }

        // Add a wrapping element for contextual blocking.
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('data-name', $found_klaro_app->id());
        $video->parentNode->replaceChild($wrapper, $video);
        $wrapper->appendChild($video);
      }
    }

    foreach ($dom->getElementsByTagName('audio') as $audio) {
      if ($audio->hasAttribute('data-src') && $audio->hasAttribute('data-name')) {
        continue;
      }

      $sources = [];
      if (!$audio->hasAttribute('src')) {
        $sources = [];
        foreach ($audio->childNodes as $n) {
          if ($n->nodeName === 'source') {
            $sources[] = $n;
          }
        }
        if (empty($sources)) {
          continue;
        }
        $initial_src = $sources[0]->getAttribute('src');
      }
      else {
        $initial_src = $audio->getAttribute('src');
      }
      $found_klaro_app = $this->matchKlaroApp($initial_src);

      if (!$inspect_only && $found_klaro_app) {
        if (!$audio->hasAttribute('src')) {
          foreach ($sources as $source) {
            $source->setAttribute('data-src', $initial_src);
            $source->removeAttribute('src');
            $source->setAttribute('data-name', $found_klaro_app->id());
          }
        }
        else {
          $audio->setAttribute('data-src', $initial_src);
          $audio->removeAttribute('src');
          $audio->setAttribute('data-name', $found_klaro_app->id());
        }

        // Add a wrapping element for contextual blocking.
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('data-name', $found_klaro_app->id());
        $audio->parentNode->replaceChild($wrapper, $audio);
        $wrapper->appendChild($audio);
      }
    }

    foreach ($dom->getElementsByTagName('img') as $img) {
      if ($img->hasAttribute('data-src') && $img->hasAttribute('data-name')) {
        continue;
      }
      $initial_src = $img->getAttribute('src');
      $found_klaro_app = $this->matchKlaroApp($initial_src);
      if (!$inspect_only && $found_klaro_app) {
        // Add a wrapping element for contextual blocking.
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('data-name', $found_klaro_app->id());
        $img->setAttribute('data-src', $initial_src);
        $img->removeAttribute('src');
        $img->setAttribute('data-name', $found_klaro_app->id());
        $img->parentNode->replaceChild($wrapper, $img);
        $wrapper->appendChild($img);
      }
    }

    foreach ($dom->getElementsByTagName('iframe') as $iframe) {
      if ($iframe->hasAttribute('data-src') && $iframe->hasAttribute('data-name')) {
        continue;
      }
      $initial_src = $iframe->getAttribute('src');
      $initial_path = parse_url($initial_src, PHP_URL_PATH) ?? '';

      // If remote-video matchKlaroApp against url parameter.
      if (str_ends_with($initial_path, '/media/oembed')) {
        parse_str(parse_url($initial_src, PHP_URL_QUERY), $params);
        $found_klaro_app = $this->matchKlaroApp($params['url']);
      }
      else {
        $found_klaro_app = $this->matchKlaroApp($initial_src);
      }

      if (!$inspect_only && $found_klaro_app) {
        $iframe->setAttribute('data-src', $initial_src);
        $iframe->removeAttribute('src');
        $iframe->setAttribute('data-name', $found_klaro_app->id());
      }
    }

    foreach ($dom->getElementsByTagName('input') as $input) {
      if ($input->getAttribute('type') !== 'image' || ($input->hasAttribute('data-name') && $input->hasAttribute('data-src'))) {
        continue;
      }

      $initial_src = $input->getAttribute('src');
      $found_klaro_app = $this->matchKlaroApp($initial_src);
      if (!$inspect_only && $found_klaro_app) {
        $input->setAttribute('data-src', $initial_src);
        $input->removeAttribute('src');
        $input->setAttribute('data-name', $found_klaro_app->id());
      }
    }

    foreach ($dom->getElementsByTagName('script') as $script) {
      if ($script->hasAttribute('data-src') && $script->hasAttribute('data-name')) {
        continue;
      }
      $initial_src = $script->getAttribute('src');
      $found_klaro_app = $this->matchKlaroApp($initial_src);
      if (!$inspect_only && $found_klaro_app) {
        $script->setAttribute('data-src', $initial_src);
        $script->removeAttribute('src');
        $script->setAttribute('type', "text/plain");
        $script->setAttribute('data-type', "text/javascript");
        $script->setAttribute('data-name', $found_klaro_app->id());
      }
    }

    foreach ($dom->getElementsByTagName('link') as $link) {
      if ($link->hasAttribute('data-href') && $link->hasAttribute('data-name')) {
        continue;
      }
      $initial_src = $link->getAttribute('href');
      $found_klaro_app = $this->matchKlaroApp($initial_src);
      if (!$inspect_only && $found_klaro_app) {
        $link->setAttribute('data-href', $initial_src);
        $link->removeAttribute('href');
        $link->setAttribute('type', "text/plain");
        $link->setAttribute('data-type', "text/css");
        $link->setAttribute('data-name', $found_klaro_app->id());
      }
    }

    if ($inspect_only) {
      return $html;
    }

    if ($complete_html) {
      $html = $dom->saveHTML();
      $html = mb_decode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }
    else {
      $html = Html::serialize($dom);
    }
    return $html;
  }

  /**
   * Filter text for XSS.
   *
   * @param string $text
   *   Text to be filtered.
   *
   * @return string
   *   The filtered test.
   */
  public function filterXss($text) {
    $allowed = ['a', 'strong', 'em'];
    $filtered = Xss::filter($text, $allowed);
    return $filtered;
  }

}
