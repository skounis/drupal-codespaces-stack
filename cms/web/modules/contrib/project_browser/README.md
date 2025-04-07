## Introduction

Project Browser (PB) makes it possible to find modules within your Drupal installation. It removes the need to leave the admin UI and visit Drupal.org to find and install modules. It is build to be a more intuitive experience than the module listing on Drupal.org. Only modules compatible with your site are displayed, and enhanced filtering capabilities provide a streamlined view of projects.

Project Browser queries the Drupal.org API in real-time to ensure that the content is easily accessible and up to date. (You may write a plugin to switch using the Drupal API for your own backend if you wish.)

Our goal is to make it easier to find and install modules for people new to Drupal and site builders. Developers will also find this valuable since it provides the composer commands to get the modules.

- For the description of the module visit: [https://www.drupal.org/project/project_browser](https://www.drupal.org/project/project_browser)
- To submit bug reports and feature suggestions or to track changes, visit: [https://www.drupal.org/project/issues/project_browser?categories=All](https://www.drupal.org/project/issues/project_browser?categories=All)


## Requirements

This module requires no modules outside of Drupal core.


## Installation

*If you intend to contribute to Project Browser, skip this step and use the "Contributing" instructions instead*

Install with composer: `composer require drupal/project_browser` then enable the module.


## Contributing

- Follow the [Git instructions](https://www.drupal.org/project/project_browser/git-instructions
  ) to clone project browser to your site
- In the `/project_browser` directory, install PHP dependencies with `composer install`
- In the `/project_browser/sveltejs` directory:
  - install JS dependencies with `yarn install`
  - For development, run the dev script `yarn dev` which will watch for filesystem changes
    - Note: `yarn dev` will report the app is available localhost, but it is fully available in your Drupal site at `admin/modules/browse`
  - When you are done, compile the changes with `yarn build`

_NOTE: More information is available in the contributor.md file!_

## Configuration

Navigate to Administration > Extend > Browse.

Filter by Recommended projects or All projects
Search and filter by Title, Sort By, Order and Categories
Customize results layout by List or Grid Format

## Updating fixtures for Drupal.org JSON:API

The tests in `tests/src/FunctionalJavascript/ProjectBrowserUiTestJsonApi.php` use data from local fixtures stored in the `tests/fixtures/drupalorg_jsonapi` folder instead of actual API endpoints. 

`DrupalOrgClientMiddleware.php` contains the mapping from request to fixture file.

If this data needs to change, just change the queries or mapping in the above file and run the PHP script to regenerate the fixtures:
```
php scripts/regenerate-drupalorg-jsonapi-fixture.php
```

## Maintainers

- Leslie Glynn (leslieg) - https://www.drupal.org/u/leslieg
- Chris Wells (chrisfromredfin) - https://www.drupal.org/u/chrisfromredfin
- Ron Northcutt (rlnorthcutt) - https://www.drupal.org/u/rlnorthcutt
- Tim Plunkett (tim.plunkett) - https://www.drupal.org/u/timplunkett
- Matthew Grasmick (grasmash) - https://www.drupal.org/u/grasmash

