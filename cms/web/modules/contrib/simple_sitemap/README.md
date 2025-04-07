## CONTENTS OF THIS FILE ##

 * Introduction
 * Installation
 * Configuration
 * Usage
 * Debugging
 * Extending the module
 * How Can You Contribute?
 * Maintainers

## INTRODUCTION ##

Author and maintainer: Pawel Ginalski (gbyte)
 * Drupal: https://www.drupal.org/u/gbyte
 * Homepage: https://gbyte.dev/

The module generates standard-compliant hreflang XML sitemaps to enhance your
site's SEO, notifies search engines of website changes via IndexNow and sitemap
ping protocols, and provides a framework for developing other sitemap types.

The multilingual XML sitemaps adhere to Google's new hreflang standard. Out of
the box the sitemaps index most of Drupal's content entity types including:

 * nodes
 * taxonomy terms
 * menu links
 * users
 * ...

Contributed entity types like commerce products can be indexed as well. On top
of that custom links and view pages can be added to sitemaps.

To learn about XML sitemaps, see https://en.wikipedia.org/wiki/Sitemaps.

The module also provides an API allowing to create any type of sitemap (not
necessarily an XML one) holding links to a local or remote source.

## INSTALLATION ##

See https://www.drupal.org/documentation/install/modules-themes/modules-8
for instructions on how to install or update Drupal modules.

## CONFIGURATION ##

### PERMISSIONS ###

The module permissions can be configured under
admin/people/permissions/module/simple_sitemap.

### SITEMAPS ###

It is possible to have several sitemap instances of different sitemap types with
specific links accessible under certain URLs. These sitemaps can be
configured under admin/config/search/simplesitemap. The module comes with the
default sitemap 'default' which is accessible under /sitemap.xml by default.

#### SITEMAP INDEX ####

There is also the 'sitemap index' sitemap which is disabled by default and which
can be used to index all other sitemaps.

If one wanted to create an index of all sitemaps, one would enable the sitemap
index, move it to the end in the sitemap list (admin/config/search/simplesitemap)
and set it as the default sitemap (admin/config/search/simplesitemap/settings).
Now links to all sitemaps will be available under /sitemap.xml.

### SITEMAP TYPES ###

A sitemap type is a configuration entity consisting of one sitemap generator
plugin and several URL generator plugins. These plugins can be implemented by
custom modules. The module comes with the sitemap types 'default_hreflang'
and 'index'. Sitemap types can be defined via the UI under
admin/config/search/simplesitemap/types.

### ENTITIES ###

Initially only the home page is indexed in the default sitemap. To
include content into a sitemap, visit
admin/config/search/simplesitemap/entities to enable support for entity types
of your choosing. After enabling support for an entity type, indexation settings
for each sitemap can be set by pressing 'Configure'. For entity type
bundles, one can optionally set the indexation settings right on the bundle's
configuration pages, e.g. admin/structure/types/manage/[content type] for nodes.

When including an entity type or bundle into a sitemap, the priority setting
can be set which will set the 'priority' parameter for all entities of that
type. Same goes for the 'changefreq' setting. All Images referenced by the
entities can be indexed as well. See https://en.wikipedia.org/wiki/Sitemaps to
learn more about these parameters.

Inclusion settings of bundles can be overridden on a per-entity
basis. Just head over to a bundle instance edit form (e.g. node/1/edit) to
override its sitemap settings.

If you wish for the sitemap to reflect the new configuration instantly, check
'Regenerate sitemaps after clicking save'. This setting may only appear if a
change in the settings has been detected.

Once sitemaps are set up in admin/config/search/simplesitemap, all the
above settings can be configured and overwritten on a per sitemap basis right
from the UI.

As the sitemaps are accessible to anonymous users, bear in mind that only links
will be included which are accessible to anonymous users. There are no access
checks for links added through the module's hooks (see below).

### VIEWS ###

To index views, enable the included, optional module Simple XML Sitemap (Views)
(simple_sitemap_views).

Simple views as well as views with arguments can be indexed on a per-sitemap
basis on the view edit page. For views with arguments, links to all view
variants will be included in the sitemap.

### CUSTOM LINKS ###

To include custom links into a sitemap, visit
admin/config/search/simplesitemap/custom.

### SEARCH ENGINES ###

The module can submit sitemap changes to search engines as well as notify search
engines of entity changes directly.
These functionalities are available through the included simple_sitemap_engines
submodule. After enabling this module, go to
admin/config/search/simplesitemap/engines/settings to set it up.

#### SITEMAP SUBMISSION ####

It is possible to have the module automatically submit specific sitemaps to search
engines. New engines can be added programmatically via simple_sitemap_engine
entities. Specific sitemaps can be submitted to specific search engines,
the time interval is configurable.

The industry is deprecating the sitemap ping protocol; use the IndexNow protocol
if applicable.

#### INDEXNOW SUBMISSION ####

The module also supports the IndexNow service provided by Bing and Yandex. The
two search engines are preconfigured and new engines can be added
programmatically via simple_sitemap_engine entities.

For the submission to work, a key needs to be generated under
admin/config/search/simplesitemap/engines/settings. This key will be saved to
Drupal's state, but it is recommended to store it in the `settings.php` or
`settings.local.php` file by adding the line
`$settings['simple_sitemap_engines.index_now.key'] = xxx;`

Do not forget to include entities under
admin/config/search/simplesitemap/entities.

### PERFORMANCE ###

The module can be tuned via UI for a vast improvement in generation speeds on
huge sites. To speed up generation, go to
admin/config/search/simplesitemap/settings and increase
'Entities per queue item' and 'Sitemap generation max duration'.

Further things that can be tweaked are unchecking 'Exclude duplicate links' and
increasing 'Maximum links in a sitemap'.

These settings will increase the demand for PHP  execution time and memory, so
please make sure to test the sitemap generation behavior. See
'PERFORMANCE TEST'.

### OTHER SETTINGS ###

Other settings can be found under admin/config/search/simplesitemap/settings.

## USAGE ##

The sitemaps are accessible to the whole world under [sitemap]/sitemap.xml.
Additionally, the default sitemap is accessible under /sitemap.xml. To view the
XML source, press ctrl+u.

If the cron generation is turned on, the sitemaps will be regenerated according
to the 'Sitemap generation interval' setting.

A manual generation is possible on admin/config/search/simplesitemap. This is
also the place that shows the overall and sitemap specific generation status.

The sitemap can also be generated via drush:
 * `simple-sitemap:generate` or `ssg`: Generates the sitemaps (continues
   generating from queue, or rebuilds queue for all sitemaps beforehand if
   nothing is queued).

 * `simple-sitemap:rebuild-queue` or `ssr`: Deletes the queue and queues elements
   for all or specific sitemaps. Add `--variants` flag and specify a
   comma separated list of sitemaps if you intend to queue only specific
   sitemaps for the upcoming generation.

Generation of hundreds of thousands of links can take time. Each sitemap gets
published as soon as all of its links have been generated. The previous version
of the sitemap is accessible during the generation process.

## Debugging ##

### PERFORMANCE TEST ###

The module includes a script that can be used to test the sitemap generation
performance.

Run `drush scr --uri http://example.com modules/simple_sitemap/tests/scripts/performance_test.php`
on your production environment to calculate generation speed and the amount of
queries performed.

If testing on a non-production environment, you can generate dummy content prior
to generation:
`drush scr --uri http://example.com modules/simple_sitemap/tests/scripts/performance_test.php -- generate 500`

## EXTENDING THE MODULE ##

### API ###

There are API methods for altering stored inclusion settings, status queries and
programmatic sitemap generation. These include:
 * simple_sitemap.generator
   * setSitemaps
   * getSitemaps
   * getDefaultSitemap
   * getSetting
   * saveSetting
   * getContent
   * generate
   * queue
   * rebuildQueue
   * entityManager
     * setSitemaps
     * getSitemaps
     * enableEntityType
     * disableEntityType
     * setBundleSettings
     * getBundleSettings
     * getAllBundleSettings
     * removeBundleSettings
     * setEntityInstanceSettings
     * getEntityInstanceSettings
     * removeEntityInstanceSettings
     * bundleIsIndexed
     * entityTypeIsEnabled
   * customLinkManager
     * setSitemaps
     * getSitemaps
     * add
     * get
     * remove

These service methods can be used/chained like so:

```php
// Create a new sitemap 'test' of the default_hreflang sitemap type.
\Drupal\simple_sitemap\Entity\SimpleSitemap::create(['id' => 'test', 'type' => 'default_hreflang', 'label' => 'Test'])->save();

/** @var \Drupal\simple_sitemap\Manager\Generator $generator */
$generator = \Drupal::service('simple_sitemap.generator');

// Set some random settings (global, not sitemap specific).
if ($generator->getSetting('cron_generate')) {
  $generator
    ->saveSetting('generate_duration', 20000)
    ->saveSetting('base_url', 'https://test');
}

// Set an entity bundle to be indexed in the 'default' and 'test' sitemaps.
$generator
  ->entityManager()
  ->enableEntityType('node')
  ->setSitemaps(['default', 'test']) // All following operations will concern these sitemaps.
  ->setBundleSettings('node', 'page', ['index' => TRUE, 'priority' => 0.5]);

// Remove all custom links from the 'default' and 'test' sitemaps and Set a
// custom link to be indexed in the 'test' sitemap.
$generator
  ->customLinkManager()
  ->remove() // Remove all custom links from all sitemaps.
  ->setSitemaps(['test']) // All following operations will concern these sitemaps.
  ->add('/some/view/page', ['priority' => 0.5]);

// Queues the 'test' sitemap for generation and generates it.
$generator
  ->rebuildQueue()
  ->generate();
```

To query data of and manipulate a specific sitemap, load it and use its
various methods. Some arbitrary example:

```php
$sitemap = \Drupal\simple_sitemap\Entity\SimpleSitemap::load('default');
// Be aware, that $sitemap->status() only returns TRUE if the sitemap is enabled
// and published. To check if it is enabled only, use $sitemap->isEnabled().
if ($sitemap->status() && !$sitemap->isDefault() && $sitemap->getCreated() < $some_timestamp) {
  $sitemap->disable();
}
```

See https://gbyte.dev/projects/simple-xml-sitemap and code documentation for
further details.

### API HOOKS ###

It is possible to hook into link generation by implementing
`hook_simple_sitemap_links_alter(&$links, $sitemap){}` in a custom module and altering the
link array shortly before it is transformed to XML.

Adding arbitrary links is possible through the use of
`hook_simple_sitemap_arbitrary_links_alter(&$arbitrary_links, $sitemap){}`. There are no
checks performed on these links (i.e. if they are internal/valid/accessible)
and parameters like priority/lastmod/changefreq have to be added manually.

Altering sitemap attributes and sitemap index attributes is possible through the
use of `hook_simple_sitemap_attributes_alter(&$attributes, $sitemap){}` and
`hook_simple_sitemap_index_attributes_alter(&$index_attributes, $sitemap){}`.

Altering URL generators is possible through
the use of `hook_simple_sitemap_url_generators_alter(&$url_generators){}`.

Altering sitemap generators is possible through
the use of `hook_simple_sitemap_sitemap_generators_alter(&$sitemap_generators){}`.

Sitemaps as well as sitemap types can be altered through the usual entity hooks.

### WRITING PLUGINS ###

There are two types of plugins that allow to create any type of sitemap. See
the generator plugins included in this module and check the API docs
(https://www.drupal.org/docs/8/api/plugin-api/plugin-api-overview) to learn how
to implement plugins.

#### SITEMAP GENERATOR PLUGINS ####

This plugin defines how a sitemap type is supposed to look. It handles all
aspects of the sitemap except its links/URLs.

#### URL GENERATOR PLUGINS ####

This plugin defines a way of generating URLs for a sitemap type.

Note:
Overwriting the default EntityUrlGenerator for a single entity type is possible
through the flag `"overrides_entity_type" = "[entity_type_to_be_overwritten]"` in
the settings array of the new generator plugin's annotation. See how the
EntityUrlGenerator is overwritten by the EntityMenuLinkContentUrlGenerator to
facilitate a different logic for menu links.

See https://gbyte.dev/projects/simple-xml-sitemap for further details.

## HOW CAN YOU CONTRIBUTE? ##

 * Report any bugs, feature or support requests in the issue tracker; if
   possible help out by submitting patches.
   http://drupal.org/project/issues/simple_sitemap

 * Do you know a non-English language? Help to translate the module.
   https://localize.drupal.org/translate/projects/simple_sitemap

 * If you would like to say thanks and support the development of this module, a
   donation will be much appreciated.
   https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5AFYRSBLGSC3W

 * Feel free to contact me for paid support: https://gbyte.dev/contact

## MAINTAINERS ##

Current maintainers:
 * Pawel Ginalski (gbyte) - https://www.drupal.org/u/gbyte
 * Andrey Tymchuk (WalkingDexter) - https://www.drupal.org/u/walkingdexter
