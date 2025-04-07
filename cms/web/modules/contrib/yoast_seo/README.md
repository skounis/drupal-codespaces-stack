
Real-time SEO for Drupal 8
----------------------
Improve your Drupal SEO: write better content using Real-time SEO for Drupal
module together with the Yoast SEO library.
https://www.drupal.org/project/yoast_seo

HOW DOES IT WORK
----------------
Real-time SEO for Drupal 8 will help you optimizing you your content for SEO.

When you edit a page, you will notice a new Real-time SEO section.
This section will give you information about your SEO optimization.
* You will need to set a focus keyword that represents the main keyword you
page is supposed to represent. Read more here : https://yoast.com/focus-keyword/
* After setting the focus keyword, a score will be displayed at the right
of the focus keyword field. It tells you how good is your content
optimization for the current page.
* Below, you will find an analysis tool that will explain all the things you can
change to improve your content optimization, and your SEO.

HOW TO CONFIGURE
----------------
By default, the Real-time SEO field is enabled for all the content types. You
can change this setting in Configuration > Development > Real-time SEO Admin Settings.
The Real-time SEO module only works on entity types that implement
ContentEntityInterface (node, media and block_content) and on taxonomy term pages.

Make sure you have the "Real-time SEO" field under "Manage form display"
Make sure you have the "Meta tags" field under "Manage form display"
Make sure you have the "URL alias" field under "Manage form display"
(Structure > Content types > [Your type] > Manage form display)

These fields are required for the Real-time SEO module to work.

HOW TO UNINSTALL
----------------
Before uninstalling Real-time SEO for Drupal 8, you need to
prepare the module to be uninstalled.
To do so, execute this command in your console, at the drupal root : drush ypu
