# Selective Better Exposed Filters
INTRODUCTION
------------
Provide extra option for better exposed filters to show only used terms.
This module is very simple and just add a part of fuctionality of
[Views Selective Filters](https://drupal.org/project/views_selective_filters)
to Better Exposed Filters module.
Module provide checkbox "Show only used items" and work only with field based
references provided by core, but don't work with "Has taxonomy term" filter.

REQUIREMENTS
------------

This module requires the following modules:
 * [Better Exposed Filters](https://drupal.org/project/better_exposed_filters)
 * [For any reference support - patch from issue]
(https://www.drupal.org/project/drupal/issues/2429699)

INSTALLATION
------------

It's very easy. You need just:
 - Enable module
 - Change settings of Better Exposed Filter in your view as usual

### Composer
If your site is [managed via Composer](https://www.drupal.org/node/2718229),
use Composer to download the module:
   ```sh
   composer require "drupal/selective_better_exposed_filters"
   ```

CONFIGURATION
-------------

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will add few options to Better Exposed Filter settings
inside the View.


MAINTAINERS
-----------

Current maintainers:
* Aleksander Riumshin (stomusic) (https://www.drupal.org/u/stomusic)
