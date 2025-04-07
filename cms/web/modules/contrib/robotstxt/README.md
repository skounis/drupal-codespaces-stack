# RobotsTxt <!-- omit in toc -->

Use this module when you are running multiple Drupal sites from a single
code base (multisite) and you need a different robots.txt file for each
one. This module generates the robots.txt file dynamically and gives you
the chance to edit it, on a per-site basis, from the web UI.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/robotstxt).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/robotstxt).

For developers, you can automatically add paths to the robots.txt file by
implementing hook_robotstxt(). See
[robotstxt.api.php](https://git.drupalcode.org/project/robotstxt/blob/8.x-1.x/robotstxt.api.php)
for more documentation.


## Table of contents

- [Table of contents](#table-of-contents)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [Frequently asked questions](#frequently-asked-questions)
- [Maintainers](#maintainers)


## Requirements

No additional modules or libraries are required, but other modules can
extend the functionality of, or integrate with, Key.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

Once you have the RobotsTxt modules installed, make sure to delete or rename
the robots.txt file in the root of your Drupal installation. Otherwise, the
module cannot receive requests for the /robots.txt path, your webserver will
serve the static file first.

If you use Drupal Scaffolding, you need to update the composer.json file to
ignore robots.txt. In your composer.json file, add (or update) the
"file-mapping" section under "extras | drupal-scaffold":

```json
     "drupal-scaffold": {
         ...
         "file-mapping": {
             "[web-root]/robots.txt": false
         }
     }
```

This will make composer ignore the robots.txt file


## Configuration

Configure the robots.txt content in Administration » Configuration »
Search and metadata » RobotsTxt.


## Troubleshooting

If the /robots.txt path is not returning your configured file, check:

- Have you deleted or moved your webroot's robots.txt file?

- Do you have clean URLs disabled?

- Do you have the fast 404 feature enabled?


## Frequently asked questions

**Q: Can this module work if I have clean URLs disabled?**

**A:** Yes it can! In the .htaccess file of your Drupal's root directory, add the
   following line to the mod_rewrite section, immediately after the line
   that says "RewriteEngine on":

```.htaccess
RewriteRule ^(robots.txt)$ index.php?q=$1
```

**Q: Does this module work together with Drupal Core "Fast 404 pages" feature?**

**A:** Yes, but you need to add robots.txt to the 'exclude_paths' of your
   settings.php.

Default Drupal Fast404 configuration (in settings.php):

```php
$config['system.performance']['fast_404']['exclude_paths'] =
   '/\/(?:styles)|(?:system\/files)\//';
```

New Drupal Fast404 configuration (in settings.php) to allow RobotsTxt module:

```php
$config['system.performance']['fast_404']['exclude_paths'] =
  '/\/(?:styles)|(?:system\/files)\/|(?:robots.txt)/';
```

**Q: How can I install the module with custom default robots.txt?**

**A:** The module _upon install only_ allows adding a default.robots.txt to the
defaults folder.

   1. Remove the robots.txt from site root.
   2. Save your custom robots.txt to "/sites/default/default.robots.txt"
   3. Run the module installation.

**Q: Is there a way to automatically delete robots.txt provided by Drupal core?**

**A:** Yes, if you are using composer to build the site, you can add a command
   into your composer.json that will make sure the file gets deleted. Depending
   on your project's structure, you will need to add one of the two following
   sections into the composer.json of your root folder:

   If the drupal site root folder is the same as your repository root folder:

```json
"scripts": {
   "post-install-cmd": [
      "test -e robots.txt && rm robots.txt || echo robots.txt is setup"
   ],
   "post-update-cmd": [
      "test -e robots.txt && rm robots.txt || echo robots.txt is setup"
   ]
}
```

or,

   if the drupal site root folder is web/ :

```json
"scripts": {
   "post-install-cmd": [
      "test -e web/robots.txt && rm web/robots.txt || echo robots is setup"
   ],
   "post-update-cmd": [
      "test -e web/robots.txt && rm web/robots.txt || echo robots is setup"
   ]
}
```

The script will run every time you do a composer install or composer update.

Please note: Only scripts defined on composer.json on the root folder will be
executed. See `https://getcomposer.org/doc/articles/scripts.md`


## Maintainers

- Christopher Martin - [ccjjmartin](https://www.drupal.org/u/ccjjmartin)
- Mike Golding - [mikeegoulding](https://www.drupal.org/u/mikeegoulding)
- Todd Nienkerk - [todd-nienkerk](https://www.drupal.org/u/todd-nienkerk)
- David Strauss - [david-strauss](https://www.drupal.org/u/david-strauss)

This project has been sponsored by:

- FOUR KITCHENS
Our team creates digital experiences that delight, scale, and deliver
measurable results. Whether you need an accessibility audit, a dedicated
support team, or a world-class digital experience platform, the Web Chefs
have you covered. Visit [fourkitchens](https://www.fourkitchens.com) to learn more.
