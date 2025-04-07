CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Use case

INTRODUCTION
------------
This module implements a simple search form with input and submit button.
It allows to redirect the user to a page with a GET parameter.
e.g. /search?search_api_fulltext=usertext

REQUIREMENTS
------------
None

RECOMMENDED MODULES
-------------------
* Search API https://www.drupal.org/project/search_api
* Search API Autocomplete https://www.drupal.org/project/search_api_autocomplete

INSTALLATION
------------
Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
for further information.

CONFIGURATION
-------------
* Add the "Simple search form" block in Structure > Block layout > Place block.
* Setup "Simple search form" block configuration.
* Click "Save block".

USE CASE
--------
The parameter matches search API fulltext search or view exposed search filter.
