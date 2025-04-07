# Drupal Recipe Installer Kit
If you thought the Drupal CMS installer was really slick, you might have wondered how to adapt it for your own nefarious purposes. Until now, it was impossible; your only option was to completely fork the installer.

This package is a toolkit to create install profiles that work similarly to the Drupal CMS installer -- the same user flow and functionality. But you can customize the stuff that really matters:
* Which recipes are shown to users at the beginning
* Which recipes are _always_ applied
* Where users should be redirected afterwards
* How the installer looks and feels

This is for developers who want a better, recipe-based installer experience, but don't want to write a pile of complicated PHP code to bend the Drupal installer to their will.

## How to use
First, require this package into your project:
```shell
composer require drupal/recipe_installer_kit
```
Then, use Drush to generate a stub install profile:
```shell
drush generate recipe-kit:installer --destination=profiles/SOME_MACHINE_NAME
```
Then go to the profile's directory and start editing the `SOME_MACHINE_NAME.info.yml` file.

To edit the look and feel create a new theme -- just a regular old theme -- in the `themes` subdirectory of the profile, and add this to the profile's info file:
```yaml
distribution:
  install:
    theme: MACHINE_NAME_OF_THEME
```

Profiles generated from this package should include this package as a Composer dependency. This means that your generated profile should have a `composer.json` that contains the following:
```json
"require": {
    "drupal/recipe_installer_kit": "*"
}
```

An example use case is [Drupal CMS's project template](https://git.drupalcode.org/project/cms), which uses this package to generate its install profile.
