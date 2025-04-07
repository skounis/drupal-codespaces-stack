# CAPTCHA: Friendly Captcha

The Friendly Captcha module uses the Friendly Captcha web service.
For more information about Friendly Captcha, please visit:
https://friendlycaptcha.com/

For a full description of the module, visit the
[project page](https://www.drupal.org/project/friendlycaptcha).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/friendlycaptcha).


## Requirements

This module requires the following modules:

- [Captcha](https://www.drupal.org/project/captcha)


## Installation

- Install as you would normally install a contributed Drupal module. Visit
  https://www.drupal.org/node/1897420 for further information.

- Copy the friendly-challenge javascript library to /libraries/friendly-challenge
  URL: https://unpkg.com/friendly-challenge@0.9.5/widget.min.js
  (resulting in: /libraries/friendly-challenge/widget.min.js)

   - Hint: Depending on your composer template / installation method the libraries
     folder might be found in /web or /htdocs.


## Configuration

1. Enable Friendly Captcha and CAPTCHA modules in:
   `admin/modules`
2. You'll now find the configuration tab in the CAPTCHA
   administration page available at:
   `admin/config/people/captcha/friendlycaptcha`
3. Select the "API endpoint". For the option "This Drupal site - limited to captches
   only.", you can skip step 4 and 5 and jump to step 6.
4. Register your web site at
   `https://app.friendlycaptcha.com/account`
5. Input the sitekey and API Key into the Friendly Captcha settings.
6. Visit the Captcha administration page and set where you
   want the Friendly Captcha form to be presented:
   `admin/config/people/captcha`
