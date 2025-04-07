**V.3.0.4**

Includes:
  - Issue #3512989: Move close button to header

**V.3.0.3**

Includes:
  - Issue #3508693: Add base_path to klaro_placeholder.js.
  - Issue #3512275: Bugfix TypeError in Drupal\klaro\Utility\KlaroHelper::matchKlaroApp()

**V.3.0.2**

Includes:
  - Issue #3512019: Add Umami Analytics service

**V.3.0.1**

Includes:
  - Issue #3484844: Show title on notice dialog optional
  - Issue #3498377: Add configurable description for purpose
  - Issue #3500091: Add link to consent manager in contextual dialog.
  - Issue #3495242: Add service for Simple Google Maps

**V.3.0.0**

Includes:
  - Issue #3491520: Add Input Filter for Klaro!
  - Issue #3497609: Remove unused klaro.admin.css.
  - Issue #3495342: Add active theme as class to klaro-element and provide Option "Adjust the UI to Drupal themes" (manual update required).
  - Issue #3498415: Set default expiration time for Klaro! cookie to 180 days (manual update required).
  - Issue #3495565: Config for Drupal module Google Tag 2.x (manual update required).
  - Issue #3493235: Config "Autofocus" should be configurable.
  - Issue #3492808: Add placeholder example for Callback.
  - Issue #3492815: Add placeholder example for Service Attachments textarea.

**V.3.0.0-rc16**

Includes:
  - Issue #3495295: Missing some translations.

**V.3.0.0-rc15**

Includes:
  - Issue #3493822: Defer loading of library.
  - Issue #3491706: Allow blocking reCaptcha
  - Issue #3494891: Update Klaro JS and use smaller JS file.
  - Issue #3494556: Add Klaro! services for AI features
  - Issue #3494891: Changed composer constraint.
  - Issue #3491681: Use klaro-no-translations-no-css.js and new library path.
  - Issue #3484568: Contextual Consent: Add optional textfield and option contextualConsentOnly
  - Issue #3491906: Move decoration of custom elements from HTML-Helper to JS
  - Issue #3493374: Ampersand in module name gets not escaped.
  - Issue #3493540: Only load Klaro! library if required
  - Issue #3492092: Support Video Embed Field

**V.3.0.0-rc14**

Includes:
  - Issue #3492492: Bugfix for Leaflet Service
  - Issue #3491622: Renamed services for Klaro and CMS and service category CMS (only for new installations).
  - Issue #3491508: Renamed project to "Klaro Cookie & Consent Manager"
  - Issue #3491521: Added tugboat config

**V.3.0.0-rc13**

Includes:
  - Fixed typos in KlaroAppForm.php
  - Introduced: Use Klaro with custom code and ID
  - Issue #3491559: Translating "Accept once" changes translation for "Yes" on whole system
  - Issue #3490656: Fixed Bug: Wrong attribute "aria-role"
  - Issue #3491325: Add service to handle Leaflet (maps)
  - Issue #3489475: Fixed Bug: A string context for threads is required

**V.3.0.0-rc12**

  - Revised texts.

**V.3.0.0-rc11**

Includes:
  - Issue #3488995: Fixed Padding on <button> Tags affects Klaro icon.

**V.3.0.0-rc10**

Includes:
  - Issue #3488390: Fixed bug: oEmbed broken for multi-language sites.
  - Issue #3403658: Fixed bug for changing klaro texts.
  - Issue #3483397: Encode and decode special UTF8-chars before working with HTML. See also #3488214
  - Issue #3487514: Added base php unit tests.
  - Issue #3484938: Add a "silent mode" (no consent, only blocking) by default.
  - Issue #3484938: Don't show toggle button by default.
  - Issue #3485880: Make close button X visible and as DeclineAll button for mustConsent: true (needed e.g. for Italy)
  - Issue #3486631: Read thumbnail and title from data-attribute for contextual consent.
  - Issue #3486340: Button style for learn more broken.
  - Issue #3485286: Add preprocess_field processor for field formatter HTML (module html_field_formatter).
  - Issue #3485286: Added several service-templates for social media platforms.
  - Issue #3484996: Changed color from green to Olivero blue.
  - Issue #3484996: use light Klaro! mode as default.

**V.3.0.0-rc9**

Includes:
  - Issue #3485035: Wording: use "Service" instead of "App" consequently.
  - Issue #3485035: Revised and harmonized texts. If updating the config-texts do not change.
  - Issue #3485055: Re-enable the composer.libraries.json for backwards compatibility.

**V.3.0.0-rc8**

Includes:
  - Issue #3484976: Add an aria-haspopup attribute to the toggle button.
  - Issue #3467872: Use only langcode part for settings.
  - Issue #3476193: Enable logging unknown resources without enabling blocking or process final HTML.
  - Issue #3483896: Fix broken ARIA reference.
  - Issue #3484539: All App's config will be loaded while installation but set inactive.
  - Issue #3483378: Added app configs for youtube and vimeo, enabled by default.
  - Issue #3483378: Added update hook to enable preprocess_field per default after update.
  - Issue #3483378: Add preprocess_field for field type iframe (contrib module iframe)
  - Issue #3483378: Add preprocess_field for field formatter oembed (media core)
  - Require drupal/klaro_js in composer.json and added install alternative in README. Removed composer.libraries.json
  - Minimum version Drupal 10.2
  - Fixed URLs in README and changelog
  - Small code refactoring (Drupal Coding Standard) - see [#dee10934](https://git.drupalcode.org/project/klaro/-/commit/dee10934829104ed8df85d0a9edcfd919a2c13b7)
  - Enabled Gitlab-CI
  - Fixed CSS (styleLint)
  - Fixed spell (some translations may be renewed also) - see [src/Form/KlaroAppForm.php](https://git.drupalcode.org/project/klaro/-/commit/dee10934829104ed8df85d0a9edcfd919a2c13b7#4a38b79831e98c32b5923a82c2ea709de61cca1a) and [src/Form/SettingsForm.php](https://git.drupalcode.org/project/klaro/-/commit/dee10934829104ed8df85d0a9edcfd919a2c13b7#20bd4d9721da31a47ba8ed5f5b14505e5c18b7ca)

**V.3.0.0-rc7**

Includes:
  - Issue #3442862 Drupal 11 compatibility fixes
  - Issue #3459210 Bump klaro lib to 0.7.22
  - Issue #3428601 add backwards compatibility for old library package-name
  - Issue #3439828 Remove limit of 255 characters for app-description
  - Issue #3439900 Add context to translatable strings
  - Issue #3441735 Turn internal protocols to actual links ()
  - Issue #3459201 Added keyboardaccessibility and aria attributes to
    consent dialog
  - Issue #3459209 refactored klaro.drupal.js including bug fix for
    callbackcode
  - Issue #3164342 Refactor check(deprecated)Library methods + display
    error/warning messages in the klaro settings form if lib missing or
    permissions not set
  - Issue #3459261 Fix HtmlMediaElements loading paused due to missing
    src attribute

Notes:
  - Please make sure to use the klaro lib in V 0.7.22, if you use the
    recommended way with the composer.libraries.json you can use use composer
    update drupal/klaro.
  - Warning, adding the context to translatable string might affect your
    current translations.
  - Added drupal 11 compat and **dropped** drupal 9 compat!! only upgrade if
    you are on ^10.1 || ^11

**V.3.0.0-rc6**

includes:
  - Issue #3405488: Process_descriptions format homepage and privacy url as
    links
  - Issue #3404693: Aggregation of klaro may cause issues depending on markup
    of page
  - Issue #3404691: Add config schema for new config...
  - Issue #3395120: Error if ajaxCommand returns empty string
  - Issue #3385543: Add callback to config form, adjusting form description
  - Issue #3161254: Text translations not working properly
  - Issue #3374083: Optionally display "learnMore"-link as button

**V.3.0.0-rc5**

includes:
  - Issue #3367642: Toggle button outside of body
  - Issue #3367229: Contextual consent dialog uses the machine name

**V.3.0.0-rc4**

includes:
  - DCS
  - Issue #3365688: Process final HTML breaks inline script within escaped
    html.
  - Issue #3365607: Add a {purposes} placeholder for the
    Manage-Apps-description.
  - Issue #3365619: The text "Manage apps Privacy policy text" is not 
    rendered.
  - Issue #3365607: Add a {purposes} placeholder also for the Manage
    Apps -> description.
  - Issue #3365599: Mention cookies in default texts.
  - Issue #3365598: Use textarea for App-description.
  - Process final HTML only for text/html and AjaxReponse.
  - Set restrict access to administer klaro permission instead of use klaro+

**V.3.0.0-rc3**

Fixes:
  - Fixture for wrapper_identifier not returning array.
  - Adjusting update hook description.
  - Add missing config htmlTexts to SettingsForm.
  - Sanitized svg

**V.3.0.0-rc2**

Fixes:
  - Issue #3357594 by sascha_meissner: Remote video field compat.
  - Issue #3356533 by hezounay, sascha_meissner: Use 
    LoggerChannelFactoryInterface.
  - Issue #3352657 PHPCS.

**V.3.0.0-rc1**

Support klaro V0.7.18
  - Rename config.apps to config.services
  - Replace klaro.show and klaro.renderKlaro with klaro.setup
  - Replace data-no-autoload attribute with config.noAutoLoad
  - Support klaro css-variable overwrites (config.styling)
  - Support additional css classes for klaro element
    (config.additionalClass)
  - Support notice as modal (config.noticeAsModal)

New features
  - Exclude uri´s (Disable Klaro and block attributed resources)
  - Disabled uri´s (Disable Klaro and dont block attributed resources)
  - Toggle dialog button optionally with optionally icon path
  - Configurable automatic blocking of unknown external resources
  - Configurable watchdog entries when blocking unknown external resources
  - Support for klaro´s Contextual Blocking (automatic attribuation)
      - Modify the final html with kernel-response-listener
      - Support iframe, img, audio, video tags, link-tags and input type
        image with src
      - Make texts in contextual blocking element editable and translatable
      - Modify the html of ajax commands of insert group
      - Configure auto-attribuation (js_alter/page_attachments/final_html)
  - Added a composer.libraries.json to easily install the required js-lib

Drupal 10 / php8.1 compat
  - Updated core-requirements
  - replacing jquery/once with core/once
  - Removing deprecations and use codingstandards phpstan/phpcs

Resolving drupal.org Issues
  - Resolving issue 3195352 (klaro.renderKlaro is not a function)
  - Resolving issue 3181417 (Hide Klaro Consent Management on some pages)
  - Resolving issue 3164353 (Cache metadata not present)
  - Resolving issue 3239581 (JS files added via AJAX will not load)

Other
  - Remove jquery dependency
