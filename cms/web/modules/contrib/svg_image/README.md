# SVG Image

SVG Image module changes default image field widget and formatter to allow use
SVG image with the standard Image field.

Using SVG Image module you will not need to use another field to use SVG image
instead of the already created Image field. Just add the `svg` extension into
the field settings and module will do the rest.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Maintainers](#maintainers)

## Requirements

This module requires:

* Drupal core module "Image"
* libxml PHP extension (part of the SVG Sanitizer)

## Installation

Install as you would normally install a contributed Drupal module. Visit:
https://www.drupal.org/docs/extending-drupal/installing-drupal-modules for
further information.

If responsive image support is needed please also enable SVG Image Responsive
(`svg_image_responsive`) module.

## Configuration

All configuration is available via Image field settings.
You can set up:

- Display image as SVG (Using `<svg>` HTML tag). `<img>` tag will be used
otherwise
- Display SVG image with predefined *width* and *height* (It will not be applied
to the other image types)

# Maintainers

* [Yaroslav Lushnikov (imyaro)](https://www.drupal.org/user/2870933)

Drupal8 version of the module was developed with
[DrupalJedi](https://www.drupal.org/drupaljedi) support.

Further development and support provided by
**[Attico International](https://www.drupal.org/node/3048850)**
