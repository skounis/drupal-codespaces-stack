# Klaro Consent Management

## CONTENTS OF THIS FILE

 * Introduction
 * Installation
 * Requirements
 * Recommended modules
 * Configuration / Customization
 * Automatic attribution of resources
 * Cookies
 * Use Klaro with custom preprocess_field
 * Use Klaro with custom code and ID
 * Contextual Consent Dialog
 * Troubleshooting
 * Maintainers

## INTRODUCTION

This module implements the [Klaro! consent manager JS-Library](https://github.com/klaro-org/klaro-js)
for Drupal and adds an interface to configure and customize Klaro!, manage
services and purposes, manage texts and their translations as well as
automatically setting the required html-attributes to external resources
for the JS-library to work. It also adds the ability to block unknown (no service
configured) external resources by default.

A module documentation can also be found [online](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/klaro-consent-management).

### Purpose of this module

The primary use case for this module is to:

- **Provide** a configurable consent/cookie manager for the site visitor, to
  give (or decline) consent to predefined "services" (in former versions "apps").
- **Satisfy** the european privacy laws and directives like [GDPR](https://eur-lex.europa.eu/eli/reg/2016/679/oj) or [ePrivacy Directive](https://eur-lex.europa.eu/eli/dir/2002/58/oj)
- **Manipulate** scripts and embeds (iframe, img, audio, video) added by
  editors or Drupal and/or its modules to adhere to the users' preference.

### Goals

- Privacy by default.
- Fully customizable, manageable and translatable consents (*services*).
- Fully customizable, manageable and translatable *purposes*.
- Fully customizable and translatable texts of the consent manager.
- Automatically set all required html attributes and/or contextual blocking
  elements to external resources.
- Find all uncovered external resources.

## INSTALLATION

The installation of this module is like other Drupal modules.

1. Place the klaro Drupal module in the `modules` directory of your Drupal
   installation.
2. Install the [`klaro-org/klaro-js`](https://github.com/klaro-org/klaro-js/tags)
   javascript library in your `libraries` folder.
3. Enable the 'Klaro!' module in 'Extend': `/admin/modules`.
4. Set up user permissions: `/admin/people/permissions#module-klaro` (see below)
5. Customize settings: `/admin//config/user-interface/klaro`

For installing the Drupal module via composer use:

`composer require drupal/klaro`

The package `drupal/klaro_js` is required by drupal/klaro and will install the
javascript library. (You may have to whitelist `drupal/klaro_js` in your
definition of `repo.packagist.org` in your project's composer.json.)

The original library is [klaro-org/klaro-js](https://github.com/klaro-org/klaro-js/).
The maintainers of drupal/klaro_js keep the package synchronized with the
original package.

For further install methods see [Install Javascript Library](https://www.drupal.org/node/3487559).

## REQUIREMENTS

This module only requires the [`klaro-org/klaro-js`](https://github.com/klaro-org/klaro-js)
javascript library placed at `{web_dir}/libraries/klaro/dist`. No other Drupal modules are required.

## RECOMMENDED MODULES

* [Configuration Translation](https://www.drupal.org/docs/8/core/modules/config-translation) for multilingual sites.

## CONFIGURATION / CUSTOMIZATION

### Permissions

The Klaro! module adds two permissions:

* `Administer Klaro!` give access to the settings form in the backend.
* `Use Klaro! UI` allow clients/users to use Klaro! and manage their consents,
   so add this at least for guests/anonymous users.

### Predefined purposes

The module comes with some predefined *purposes*, such as "CMS" for
Drupal-related cookies or "Embedded external content". These are used to group
the services. You can add, change and delete purposes for your needs.

### Predefined services

The module ships some services, e.g. for Matomo open analytics platform.

You have to review and enable the services you need for your site.

#### Services for YouTube and Vimeo

Klaro offers two services for embedded external content: YouTube and Vimeo.
Both services are activated by default and take effect for the `oembed` field
of Drupal Core Media Remote Video or the `video_embed_field_video` field by
[Video Embed Field](https://www.drupal.org/project/video_embed_field) for
legacy/custom Media Entities.

#### Services for social media platforms

Klaro offers several services for embeds of social media platforms. You have
to check and adapt these services for your needs. These services are
deactivated by default and can take effect e.g. in combination with the module
[html_field_formatter](https://www.drupal.org/project/html_field_formatter).

#### Services for AI (Artificial Intelligence)

- Klaro offers a service for the new AI Chatbot (Deepchat), submodule of
[AI (Artificial Intelligence)](https://www.drupal.org/project/ai).
- Klaro offers also a service for [AI Image Alt Text](https://www.drupal.org/project/ai_image_alt_text).

#### Services for Matomo

For Matomo the module provides two different service. If you want to
track every visit and block only cookies, please use the
service `matomo_cookies`.
You have to add an additional javascript line to the Matomo config on
`/admin/config/system/matomo`. Insert in "Advanced settings" in
"Code snippet (before)" the following command:
`_paq.push(['requireCookieConsent']);`. For further information see [here](https://matomo.org/faq/how-to/using-klaro-consent-manager-with-matomo/#klaro-open-source)
and [Issue #3346662](https://www.drupal.org/project/matomo/issues/3346662).

#### Services for Google Tag Manager / Analytics / Google Consent Mode v2

The module includes services for Google Tag Manager and Google Analytics (GA4).
As GA4 is mostly used with the GTM, the GA4 service is obsolete in most cases.

The services are designed for the Drupal modules [GoogleTag Manager](https://www.drupal.org/project/gtm)
8.x-1.x,
[Google Tag](https://www.drupal.org/project/google_tag) 2.x (and
[Google Analytics 4](https://www.drupal.org/project/ga4_google_analytics).

For [Google Tag](https://www.drupal.org/project/google_tag) 2.x you may
consider to disable the noscript snippet (see [#3106318](https://www.drupal.org/i/3106318)).

Both services can be activated and control the integration of Google code.

It is also possible to integrate the Google Tag Manager (GTM) with
**Google Consent Mode v2**, see [documentation](https://klaro.org/docs/tutorials/google_tag_manager).
See also [Issue #3484827](https://www.drupal.org/project/klaro/issues/3484827).

#### Further Services

- [Leaflet](https://www.drupal.org/project/leaflet)
- [Recaptcha](https://www.drupal.org/project/recaptcha)
- [Simple Google Maps](https://www.drupal.org/project/simple_gmap)
- [Umami Analytics](https://www.drupal.org/project/umami_analytics)

### Backend / UI

* Manage general settings&nbsp;`/admin/config/user-interface/klaro`.
* Manage services&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`/admin/config/user-interface/klaro/services`.
* Manage purposes&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`/admin/config/user-interface/klaro/purposes`.
* Manage texts&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;`/admin/config/user-interface/klaro/texts`.

### Styling

At `/admin/config/user-interface/klaro` settings->styling you can add additional
css classes that will be added to the consent-notice, the consent-modal and the
contextual-blocking element, so you can apply your own css to it.

In the field `Override Klaro css variables` you can make changes to positioning
and the used theme, for example you can enter `light, right, bottom` to use the
light-theme and position the cookie notice in the bottom right corner. More 
infos you find [here](https://github.com/klaro-org/klaro-js/blob/fb4e393d2cd8aeedc3e751d103dfbfd35ffae0f2/src/themes.js)

The setting "additional css classes" will be added to all klaro elements
(cookie-notice, consent-dialog, contextual blocking element), so you can
apply your own css to it.

The setting "Adjust the UI to Drupal themes" enables the loading of
the `klaro-override.css` file. In this file, most of the Klaro!
styles are overridden to adopt a Drupal-like appearance. Customized
CSS settings for Olivero, Claro and Gin are included. You can use
the CSS variables for your own overrides. Klaro! sets the machine
name of the theme as class in the main klaro-element (e.g.
`klaro-theme-gin`). For more details, see the [css/klaro-override.css](https://git.drupalcode.org/project/klaro/-/blob/3.x/css/klaro-override.css).

For the toggle button shipped with this module, there is an override class
added `klaro_toggle_dialog_override` so you can overwrite the styles with
this css selector: `klaro_toggle_dialog.klaro_toggle_dialog_override`

### Open the Klaro! consent manager modal (e.g. as menu item)

* If the user saves a consent, the consent modal and/or cookie notice will
  disappear in the vanilla JS-Library until the cookies that save this
  information are deleted.
* This module ships a button to toggle the dialog, you can enable/disable it
  `/admin/config/user-interface/klaro` Settings->General->Show button to
  toggle the consent modal.
* If you like to provide own links or buttons to open the consent dialog at
  any time, you can just add this `rel`-attribute of any clickable element:
  `rel="open-consent-manager"`. You can use the module [link_attributes](https://www.drupal.org/project/link_attributes) for this.
* If there is / are already any `rel`-value(s) just extend the value e.g.
  `rel="nofollow open-consent-manager noindex"`.

## AUTOMATIC ATTRIBUTION OF RESOURCES

The vanilla klaro library requires you to manually add html-attributes
`data-name="{service_name}` `data-src="{src}"` and `type="text/plain`
(for scripts or links) to your script, iframe, img, audio, video etc. tags.

This module tries to automate the process as far as possible by utilizing
js_alter, attachments_alter, preprocess_field and a kernelResponseListener
that will go through the final html and attribute all external resources.
It will also take care for external resources that are added via Drupal's
ajax-insert-commands (AfterCommand, AppendCommand, BeforeCommand,
HtmlCommand,PrependCommand, InsertCommand and ReplaceCommand) as well as on
the fly added external libraries with Drupal's add_js ajax command.

It does it by matching against the advanced configuration for each service. You
find those under `/admin/config/user-interface/klaro/services/{service_name}`

In the field `sources` you can add paths as they appear in the src attribute
of script, iframe, img, video and audio tags, Enter one source per line,
partial matches are supported.

The preprocess_field handles at this time following fields:

* "oembed" field formatter (provided by Media Core)
* "video_embed_field_video" field formatter - see [Video Embed Field](https://www.drupal.org/project/video_embed_field)
* "iframe" field type - see [Iframe](https://www.drupal.org/project/iframe)
* "html" field formatter - see [HTML Field Formatter](https://www.drupal.org/project/html_field_formatter)
* "simple_gmap" field formatter - see [Simple Google Maps](https://www.drupal.org/project/simple_gmap)

If you need further field type, please open a feature request and (optionally)
open a merge request (see below).

There is also a preprocessor for [Leaflet](https://www.drupal.org/project/leaflet) mapping library.

The option "Process final HTML" parses the generated HTML and adds attributes
to all matching tags that are not attributed yet. This feature is rather
experimental, invalid or malformed html might lead to unknown behavior.

Known bug: The HTML processor destroys special chars if TWIG Debug mode is
enabled (see [Issue 3483397](https://www.drupal.org/project/klaro/issues/3483397)).

### Text Filter for Klaro!

The Klaro! module ships a Text Filter for the use with Klaro! Consent Manager.
The filter decorates all known external resources and blocks loading them
without consent. If "blocking of unknown resources" is enabled, all external
resources will be blocked.

You have to enable this filter for the Input Formats you want to protect.

We generally recommend that you do not allow such HTML tags in the editor,
but use special field types or formatter such as oEmbed (Core),
[HTML Field Formatter](https://www.drupal.org/project/html_field_formatter) or [Iframe](https://www.drupal.org/project/iframe)
or use Drupal Media.

Read more about [Text filters and Input Formats](https://www.drupal.org/node/213156).

### Automatic blocking of unknown resources

Unknown external resources can be blocked by default. This option will
automatically add an "unknown service" and let users decide to consent to
load them. The "unknown service" is only added while processing "preprocess_field"
or "final HTML".

### What will be blocked automatically:

* script tags with src attribute (only for known services)
* iframes with field type [Iframe](https://www.drupal.org/project/iframe)
* oembed with field formatter "oembed" (Core) or "video_embed_field_video" ([Video Embed Field](https://www.drupal.org/project/video_embed_field))
* html with field formatter "html" ([HTML Field Formatter](https://www.drupal.org/project/html_field_formatter))
* [Leaflet](https://www.drupal.org/project/leaflet) Maps
* [Simple Google Maps](https://www.drupal.org/project/simple_gmap)

### What will be blocked automatically with "Process final HTML":

* script tags with src attribute
* img tags with src attribute (Contextual blocking wrap will be added)
* input type "image" tags with src attribute (Contextual blocking wrap will be
  added)
* link tags with href attribute (e.g fonts)
* audio tags with src attributes or source child nodes with src attribute
  (Contextual blocking wrap will be added)
* video tags with src attributes or source child nodes with src attribute
  (Contextual blocking wrap will be added)
* Elements that match the "Embed wrapper class" configuration of an service.
  (Contextual blocking wrap will be added)
* All above dynamically loaded with AJAX, including attached libraries.

### What will **not** be blocked automatically:

* Inline script tags with a script-body that will itself load external
  resources and are not added with page_attachments and a configured
  attachments-identifier in a klaro service (You need to set the attributes
  manually to block it with klaro).
* Includes within css files.

### Logging of unknown resources

To find unknown external resources you can enable the option "Log unknown
resources" in tab "Automatic attribution" of the Library settings. After
activation, Drupal generates a log entry each time an external integration
is found in the HTML code of the page. This feature always parses the final
HTML even if the process is disabled. We recommend activating this function
only temporarily for the detection of previously unknown external resources,
as parsing the finished HTML document is time-consuming.

## COOKIES

Klaro! saves the user-decisions as cookies (you can also configure to use the
browsers localstorage). The default expiration time for the Klaro! cookie 
is set to 180 days.
Please check whether this meets the requirements of your country.

However to let klaro also delete the service cookies,
i.e. when you revoke a consent, there are two places to provide information
about them.

1. `/admin/config/user-interface/klaro/services/{klaro_service}`
   Inside the `Advanced` section you can provide specific cookie information
   about `name` (regex), `path` and `domain` for each service.
2. `/admin/config/user-interface/klaro`
   Inside the `Advanced` section you will find the `Matching cookie domains`
   textarea. Sometimes scripts may set cookies with dynamic cookie domains.
   You can add different domains here so that klaro will try to delete all
   cookies defined in your services additionally with this cookie domains. So
   you don't need to add 30 cookie information inside your service just because
   a script adds several cookies just with 10 different cookie domains.

## USE KLARO WITH CUSTOM PREPROCESS_FIELD

If you want to implement Klaro for your own field types, you can
use the functions of the KlaroHelper class.
See `klaro_preprocess_field()` in `klaro.module`:

### Example 1: Finding and replace src attribute.

```php
  /** @var \Drupal\klaro\Utility\KlaroHelper $helper */
  $helper = \Drupal::service('klaro.helper');

  // Rewrite src from field type iframe.
  if ($variables["field_type"] == "iframe") {
    foreach ($variables['items'] as $i => $item) {
      $src = $item['content']['#src'];
      $service = $helper->matchKlaroApp($src);
      if ($service) {
        $attributes = $item['content']['#attributes'];
        $attributes = $helper->rewriteAttributes($attributes, $service->id());
        $variables['items'][$i]['content']['#attributes'] = $attributes;
      }
    }
  }
```

### Example 2: Parse and replace in whole HTML snippet.

```php
  /** @var \Drupal\klaro\Utility\KlaroHelper $helper */
  $helper = \Drupal::service('klaro.helper');

  // Rewrite markup from field formatter html.
  if ($variables["element"]['#formatter'] == "html") {
    foreach ($variables['items'] as $i => $item) {
      $html = $item['content']['#children'];
      $variables['items'][$i]['content']['#children'] = $helper->processHtml($html);
    }
  }
```

## USE KLARO WITH CUSTOM CODE AND ID

In some situations, you cannot block Javascript files, but must block document elements, e.g. to block maps.

You can rewrite the ID of an HTML element and add the attributes `data-id` and `data-name` to control the behavior of these elements.

### Example: Block loading external maps by leaflet module

```php
function klaro_preprocess_leaflet_map(&$variables) {
  $variables['attributes']['data-name'] = 'leaflet';
  $variables['attributes']['data-id'] = $variables['map_id'];
  $variables['map_id'] .= '-protected';
}
```

After consent, Klaro! module will rewrite the ID and execute all Drupal
behaviors to handle it.

You need a Klaro! service with a machine name that matches `data-name`.

## CONTEXTUAL CONSENT DIALOG

Blocked markup elements with `src` attribute (like `<img>` or `<iframe>`)
show a contextual consent dialog.

Sometimes other elements need to be blocked. These elements can be
overlaid by specifying the query selector (e.g. `.my-embed .my-element`).

In the field `Embed wrapper classes` (Klaro > Service > Advanced) you can add
query selectors for which a contextual blocking element will be wrapped.
For example a twitter embed not only contains a script tag (which will be
blocked by adding the `sources` field) but also some html, in this case a
blockquote with the class "twitter-tweet".

### CUSTOMIZE TEXT FOR DIALOG

By default, `Load external content supplied by {title}?` is used for the
dialog. You can customize this text per service via
General > Contextual Consent Text. You can use the tags `<a>`, `<em>`,
`<strong>`.

### SHOW THUMBNAIL AND TITLE IN CONTEXTUAL CONSENT DIALOG

The Klaro! module looks for data-attribute `data-thumbnail` and attribute
`title` on iframe or other entities with src-attribute and inserts thumbnail
and title into contextual consent dialog.

While preprocessing fields for automatic attribution, Klaro! tries to
determine existing thumbnails and adds them to the markup.
You can disable this option in Klaro! Settings -> Automatic Attribution 
-> Determine thumbnail for preview.

Example for custom field_preprocess function:

```php
function hook_preprocess_field(&$variables) {
  $obj = $variables['element']['#object'];
  if [...] {
    $url = $helper->getThumbnail($obj);
    if ($url) {
      $variables['items'][0]['content']['#attributes']['data-thumbnail'] = $url;
    }
  }
}
```

## TROUBLESHOOTING

- If the script- / resource **element attributes** won't appear, then there may
  be some other module or the theme that is preprocessing the tags and
  stripping out these attributes.
- Issue tracker: https://www.drupal.org/project/issues/klaro

## MAINTAINERS

 * Sascha Meißner ([sascha_meissner](https://www.drupal.org/u/sascha_meissner))
 * Jan Kellermann ([jan kellermann](https://www.drupal.org/u/jan-kellermann))

Thanks to

 * Christian Kipke ([ckidow](https://www.drupal.org/u/ckidow)) for first D8 Release.
 * Jürgen Haas ([jurgenhaas](https://www.drupal.org/u/jurgenhaas)) for supporting the V3 release.
