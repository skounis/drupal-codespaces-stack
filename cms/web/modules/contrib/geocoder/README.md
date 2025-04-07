# Geocoder 4.x

This is a complete rewrite of the Geocoder module, based on the
[Geocoder PHP library](http://geocoder-php.org) - [version 4.x](https://github.com/geocoder-php/Geocoder/tree/4.x).

This branch is a parallel copy of the Geocoder 3.x branch requiring the
php-http/guzzle7-adapter, thus compatible with Guzzle 7 (that is a possible
dependency since Drupal 9.4 and a requirement for Drupal 10).
At the moment this branch still have incompatibilities with packages locked to
php-http/guzzle6-adapter dependency (see issue #3283651).

### How to seamlessly upgrade to Drupal 10 & Geocoder 4.x ... ###

When preparing for an upgrade to Drupal 10 we recommend that you widen
your composer version constraints to allow either 3.x or 4.x.
Edit composer.json the following way:

```
"require": {
...
"drupal/geocoder": "^3.20 || ^4.0",
...
}
```

# Features
* Solid API based on [Geocoder PHP library](http://geocoder-php.org);
* Geocode and Reverse Geocode using one or multiple Geocoder providers
  (ArcGISOnline, BingMaps, File, GoogleMaps, MapQuest, Nominatim,
  OpeneStreetMap, etc);
* Results can be dumped into multiple formats such as WKT, GeoJson, etc
  ...</li>
* The Geocoder Provider and Dumper plugins are extendable through a custom
  module;</li>
* Submodule Geocoder Field provides Drupal fields widgets and formatters, with
  even more options;</li>
* [Geofield](https://www.drupal.org/project/geofield) and
  [Address](https://www.drupal.org/project/address) fields integration;
* Caching results capabilities, enabled by default;

# Requirements
* [Composer](https://getcomposer.org/), to add the module to your codebase (for
  more info refer to [Using Composer to manage Drupal site
  dependencies](https://www.drupal.org/node/2718229);
* [Drush](http://drush.org), to enable the module (and its dependencies) from
  the shell;
* The external [Geocoder Provider(s)](https://packagist.org/providers/geocoder-php/provider-implementation)
  that should enabled and used in the module.
  Dependant [willdurand/geocoder](https://packagist.org/packages/willdurand/geocoder)
  and (specific provider) additional / required libraries will be downloaded
  automatically via composer.
* The embedded "Geocoder Geofield" submodule requires the [Geofield
  Module](https://www.drupal.org/project/geofield);
* The embedded "Geocoder Address" submodule requires the [Address
  Module](https://www.drupal.org/project/address);

# Installation and setup
* Download the module running the following shell command from your project root
  (at the composer.json file level):

  ```$ composer require drupal/geocoder:^4.0```

* Choose the [Geocoder Provider](https://github.com/geocoder-php/Geocoder#address)
you want to use and also add it as a required dependency to your project. For
example if you want to use Nominatim (used by OpenStreetMap) as your provider:

  ```$ composer require geocoder-php/nominatim-provider```

* Enable the module via [Drush](http://drush.org)

  ```$ drush en geocoder```

  or the website back-end/administration interface;
* Eventually enable the submodules: ```geocoder_field``` and
  ```geocoder_geofield``` / ```geocoder_address```.
* Create and configure one or more providers at Configuration > System >
  Geocoder > Providers:
  `admin/config/system/geocoder/geocoder-provider`.
* Configure caching options at Configuration > System > Geocoder:
  `admin/config/system/geocoder`.

* ### Support for [COI (Config Override Inspector) module](https://www.drupal.org/project/coi)
  It is hard to confirm that the content overrides
  are being applied correctly in productions. Also api keys are visible when
  they are being overridden in the production environment.
  The Geocoder module supports the use of the COI module to more easily see what
  has been overridden and also hide overridden apiKeys.

# Submodules
The geocoder submodules are needed to set-up and implement Geocode and Reverse
Geocode functionalities on Entity fields from the Drupal backend:
* The **geocoder_field** module adds the ability to setup Geocode operations
  on entity insert & edit operations among specific fields types so as field
  Geo formatters, using all the available Geocoder Provider Plugins and Output
  Geo Formats (via Dumpers). It also enables the File provider/formatter
  functionalities for Geocoding valid Exif Geo data present into JPG images;
  functionalities for Geocoding valid Exif Geo data present into JPG images;

* The **geocoder_geofield** module provides integration with Geofield
  (module/field type) and the ability to both use it as target of Geocode or
  source of Reverse Geocode with the other fields. It also enables the
  provider/formatter functionalities for Geocoding valid GPX, KML and GeoJson
  data present into files contents;

* The **geocoder_address** module provides integration with Address
  (module/field type) and the ability to both use it as target of Reverse
  Geocode from a Geofield (module/field type or source of Geocode with the other
  fields;

From the Geocoder configuration page it is possible to setup custom plugins
options.

Throughout geocoder submodules **the following fields types are supported**

###### for Geocode operations:

 * "text",
 * "text_long",
 * "text_with_summary",
 * "string",
 * "string_long",
 * "file" (with "geocoder_field" module enabled),
 * "image" (with "geocoder_field" module enabled),
 * "computed_string" (with "computed_field" module enabled);
 * "computed_string_long" (with "computed_field" module enabled);
 * "address" (with "address" module and "geocoder_address" sub-module enabled);
 * "address_country" (with "address" module and "geocoder_address" sub-module
    enabled);

###### for Reverse Geocode operations:

 * "geofield" (with "geofield" module and "geocoder_geofield" sub-module
    enabled);

**Note:** Geocoder Field sub-module provides hooks to alter (change and extend)
          the list of Geocoding and Reverse Geocoding fields types
(@see geocoder_field.api)

####Using Geocoder operations behind Proxy

"geocoder.http_adapter" service is based on Guzzle implementation,
that is using settings array namespaced under $settings['http_client_config'].
Geocoding behind a proxy will be correctly set by (@see default.settings.php):

$settings['http_client_config']['proxy'];

# API

## Get a list of available Provider plugins

This is the list of plugins that has been installed using Composer and are
available to configure in the UI.

```php
\Drupal::service('plugin.manager.geocoder.provider')->getDefinitions();
```

## Get a list of available Dumper plugins

```php
\Drupal::service('plugin.manager.geocoder.dumper')->getDefinitions();
```

## Get a list of Providers that are created in the UI.

```php
\Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple();
```

## Geocode a string

```php
// A list of machine names of providers that are created in the UI.
$provider_ids = ['geonames', 'googlemaps', 'bingmaps'];
$address = '1600 Amphitheatre Parkway Mountain View, CA 94043';

$providers = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
->geocode($address, $providers);
```

####Note

## Reverse geocode coordinates

```php
$provider_ids = ['freegeoip', 'geonames', 'googlemaps', 'bingmaps'];
$lat = '37.422782';
$lon = '-122.085099';

$providers = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
->reverse($lat, $lon, $providers);
```

## Return format

Both ```Geocoder::geocode()``` and ```Geocoder::reverse()```
return the same object: ```Geocoder\Model\AddressCollection```,
which is itself composed of ```Geocoder\Model\Address```.

You can transform those objects into arrays. Example:

```php
$provider_ids = ['geonames', 'googlemaps', 'bingmaps'];
$address = '1600 Amphitheatre Parkway Mountain View, CA 94043';

$providers = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
->geocode($address, $providers);
$address_array = $addressCollection->first()->toArray();

// You can play a bit more with the API

$addressCollection = \Drupal::service('geocoder')
->geocode($address, $providers);
$latitude = $addressCollection->first()->getCoordinates()->getLatitude();
$longitude = $addressCollection->first()->getCoordinates()->getLongitude();
```

You can also convert these to different formats using the Dumper plugins.
Get the list of available Dumper by doing:

```php
\Drupal::service('plugin.manager.geocoder.dumper')->getDefinitions();
```

Here's an example on how to use a Dumper:

```php
$provider_ids = ['geonames', 'googlemaps', 'bingmaps'];
$address = '1600 Amphitheatre Parkway Mountain View, CA 94043';

$providers = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
->geocode($address, $providers);
$geojson = \Drupal::service('plugin.manager.geocoder.dumper')->createInstance('geojson')->dump($addressCollection->first());
```

There's also a dumper for GeoPHP, here's how to use it:

```php
$provider_ids = ['geonames', 'googlemaps', 'bingmaps'];
$address = '1600 Amphitheatre Parkway Mountain View, CA 94043';

$providers = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
->geocode($address, $providers);
$geometry = \Drupal::service('plugin.manager.geocoder.dumper')->createInstance('geometry')->dump($addressCollection->first());
```
##Geocoder API Url Endpoints

The Geocoder module provides the following API Url endpoints (with Json output),
to consume for performing Geocode and Reverse Geocode operations respectively.

- #### Geocode
  This endpoint allows to process a Geocode operation
  (get Geo Coordinates from Addresses) on the basis of an input Address,
  the operational Geocoders and an (optional) output Format (Dumper).

  Path: **'/geocoder/api/geocode'**
  Method: **GET**
  Access Permission: **'access geocoder api endpoints'**
  Successful Response Body Format: **json**

  #####Query Parameters:

  - **address** (required): The Address string to geocode (the more detailed
  and extended the better possible results.

  - **geocoder** (required): The Geocoder id, or a list of geocoders id
  separated by a comma (,) that should process the request (in order of
  priority). At least one should be provided. Each id should correspond with a
  valid @GeocoderProvider plugin id.

    Note: (if not differently specified in the "options") the Geocoder
    configurations ('/admin/config/system/geocoder') will be used for each
    Geocoder geocoding/reverse geocoding.

  - **format** (optional): The geocoding output format id for each result.
  It should be a single value, corresponding to one of the Dumper
  (@GeocoderDumper) plugin id defined in the Geocoder module. Default value (or
  fallback in case of not existing id): the output format of the specific
  @GeocoderProvider able to process the Geocode operation.

  - **address_format** (optional): The specific geocoder address formatter
  plugin (@GeocoderFormatter) that should be used to output the
  "formatted_address" property (present when no specific output
  format/@GeocoderDumper is requested). This fallback to default (bundled))
  "default_formatted_address" @GeocoderFormatter

  - **options** (optional): Possible overriding plugins options written
  in the form of multi-dimensional arrays query-string (such as a[b][c]=d).
  For instance to override the google maps locale parameter (into italian):

  ````options[googlemaps][locale]=it````

- #### Reverse Geocode
  This endpoint allows to process a Reverse Geocode operation (get an Address
  from Geo Coordinates) on the basis of an input string of Latitude and
  Longitude
  coordinates, the operational Geocoder Providers and an (optional) output
  Format (Dumper).

  Path: **'/geocoder/api/reverse_geocode'**
  Method: **GET**
  Access Permission: **'access geocoder api endpoints'**
  Successful Response Body Format: **json**

  #####Query Parameters:

  - **latlon** (required): The latitude and longitude values, in decimal
  degrees, as string couple separated by a comma (,) specifying the location for
  which you wish to obtain the closest, human-readable address.

  - **plugins** (required): *@see the Geocode endpoint parameters description*

  - **format** (optional): *@see the Geocode endpoint parameters description*

  - **options** (optional): *@see the Geocode endpoint parameters
  description*

#### Successful and Unsuccessful Responses

If the Geocode or Reverse Geocode operation is successful each Response result
is a Json format output (array list of Json objects), with a 200 ("OK")
response status code.
Each result format will comply with the chosen output format (dumper).
It will be possible to retrieve the PHP results array with the Response
getContent() method:

````
$response_array = JSON::decode($this->response->getContent());
$first_result = $response_array[0];
````

If something goes wrong in the Geocode or Reverse Geocode operations
(no Geocoder provided, bad Geocoder configuration, etc.)
the Response result output is empty, with a 204 ("No content") response
status code. See the Drupal logs for information regarding possible Geocoder
wrong configurations causes.

## Persistent cache for geocoded points
Ref: Geocoder issue:
[#2994249](https://www.drupal.org/project/geocoder/issues/2994249)
It is possible to persist the geocode cache when drupal caches are cleared,
enabling support and configuration for the
.
- install [Permanent Cache Bin module](https://www.drupal.org/project/pcb)
- in your *settings.php add:
  `$settings['cache']['bins']['geocoder'] = 'cache.backend.permanent_database'`

# Upgrading from Geocoder 2.x to 3.x (and above))

## Site builders

1. When upgrading to the new Geocoder 8.x-3.x branch you would
   need to remove the Geocoder 8.x-2.x branch before
   (`composer remove drupal/geocoder`), and make sure also its
   dependency willdurand/geocoder": "^3.0" library is removed.
   (eventually run also: `composer remove willdurand/geocoder`);

2. Require the new default Geocoder 4.x version:
   `composer require 'drupal/geocoder:^4.0'`
   (this will also install the dependency willdurand/geocoder
   in its "^4.0" version)

3. Choose the [Geocoder Provider](https://packagist.org/providers/geocoder-php/provider-implementation)
   you want to use and also add it as a required dependency to your project.
   For example if you want to use Google Maps as your provider:
   `composer require geocoder-php/google-maps-provider`

   It will be added as geocoder provider option choice in the "add provider"
   select of the Geocoder module Providers settings page
   ('/admin/config/system/geocoder/geocoder-provider').

4. Run the database updates, either by visiting `update.php` or running the
   `drush updb` command.

5. Check the existing Geocoder provider settings or add new ones from the
   Geocoder module Providers settings page
   ('/admin/config/system/geocoder/geocoder-provider')

6. Set back (update) the Geocoding & Reverse Geocoding settings for each field
   you previously applied them, as they would have been lost in
   (won't work since) the upgrade.

## Developers

Since Geocoder 3.x version the Geocoder providers are config entities,
whereas in earlier versions the provider settings were stored in simple
configuration. An upgrade path is provided but any code that
was relying on the old simple config will need to be updated to use the config
entities instead. Take a look at the `GeocoderProvider` entity type for more
information.

### Removed methods
#### GeocodeFormatterBase::getEnabledProviderPlugins()

The method
`\Drupal\geocoder_field\Plugin\Field\GeocodeFormatterBase::getEnabledProviderPlugins()`
used to return an array of provider configuration as flat properties. It has
been replaced by
`\Drupal\geocoder_field\Plugin\Field\GeocodeFormatterBase::getEnabledGeocoderProviders()`
which returns an array of `GeocoderProvider` entities.

### Signature changes
#### Geocoder::geocode()

The method `\Drupal\geocoder\Geocoder::geocode()` used to take a string of data
to geocode as well as a list of provider plugins as an array and an optional
array of configuration overrides.

The old signature:

```
public function geocode($data, array $plugins, array $options = []);
```

Since the configuration is now stored in config entities this method now takes
an array of GeocoderProvider entities. The optional array of overrides has been
dropped since it is already possible to override the configuration using the
regular entity hooks offered by Drupal core.

The new signature:

```
public function geocode(string $data, array $providers): ?AddressCollection;
```

#### Geocoder::reverse()

The method `\Drupal\geocoder\Geocoder::geocode()` used to take the latitude and
longitude as string values, as well as a list of provider plugins as an array
and an optional array of configuration overrides.

The old signature:

```
public function reverse($latitude, $longitude,
array $plugins,
array $options = []
);
```

Since the configuration is now stored in config entities this method now takes
an array of GeocoderProvider entities. The optional array of overrides has been
dropped since it is already possible to override the configuration using the
regular entity hooks offered by Drupal core.

The new signature:

```
public function reverse(string $latitude,
string $longitude,
array $providers): ?AddressCollection;
```

### Functional changes
#### ProviderPluginManager::getPlugins()

In Geocoder 2.x `\Drupal\geocoder\ProviderPluginManager::getPlugins()` was the
main way of retrieving the provider plugins. It was returning the plugin
definitions with the provider configuration mixed into it.

Since Geocdoer 3.x this data model has been replaced by the new
`GeocoderProvider` config entity. Now this method returns the list of
plugin definitions, making it the same result as
calling ProviderPluginManager::getDefinitions().

It is recommended to no longer use this method but instead use one of these
two alternatives:

In order to get a list of all available plugin definitions:

```
$definitions = \Drupal\geocoder\ProviderPluginManager::getDefinitions();
```

In order to get a list of all geocoding providers that are configured by
the site builder:

```
$providers = \Drupal\geocoder\Entity\GeocoderProvider::loadMultiple();
```
