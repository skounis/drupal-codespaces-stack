# Sitemap

The Sitemap module displays one or more a human-readable lists of links on a page. A sitemap is a way for visitors to navigate your website using an overview of notable pages on the site. Sitemaps tend to be useful for sites with lots of lightly-organized content, for example, colleges and universities, governments, or organizations with many different units.


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Review the module's permissions at Administration -> People -> Permissions
   (i.e.: `/admin/people/permissions/module/sitemap`):
    - Most sites will want to grant "View published sitemap" to both anonymous
        and authenticated users.
    - Most sites will only want to grant "Administer sitemap" to administrators.
2. Many sites will want to enable the "Sitemap" menu item. To do so, make sure
    Drupal Core's "Menu UI" module (`menu_ui`) is enabled, then go to
    Administration -> Structure -> Menus -> Tools
    (i.e.: `/admin/structure/menu/manage/tools`). You may move the "Sitemap"
    menu item to another menu if you would like, by clicking "Edit" (i.e.:
    `/admin/structure/menu/link/sitemap.page/edit`), and changing the "Parent
    link" select list.
3. Most sites will want to configure the contents of the sitemap at
    Administration -> Configuration -> Search and metadata -> Sitemap (i.e.:
    `/admin/config/search/sitemap`):
    - The "Page title" setting is customizable.
    - If you set a "Sitemap message", it is displayed at the top of the page,
        above the sitemap.
    - You can add sections to the Sitemap by enabling some of the built-in
        plugins in the "Enable plugins" section of the form. The Sitemap module
        comes with several built-in plugins, which are explained below.
        - It is possible for a contrib or custom module to add new Sitemap
        sections (or section types) using
        [Drupal's Plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api/plugin-api-overview).
    - You can change the order of the plugins in the "Plugin display order"
        section of the form.
    - Some (but not all) of the plugins are configurable. You can configure
        plugins in  the "Plugin settings" section of the form.
    - There is an option to include the default CSS file in the "CSS settings"
        section of the form. You
4. View the sitemap at `/sitemap`, or by clicking the "View" link in the Primary
    tabs.


### Sitemap plugins

Here is an explanation of each of the plugins built into the Sitemap module.
Note that it is possible for a contrib or custom module to add new Sitemap
sections (or section types) using
[Drupal's Plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api/plugin-api-overview).
See `src/Plugin/Sitemap` in this module's code for some examples. The behavior
of Sitemap plugins defined in other modules may be documented in that module.

- The "Site front page" plugin will display a link with the link text
    "Front page of (your site name)".
    - In "Plugin settings":
        - You can change the title of the _heading_ above the link.
        - Note that you cannot change the _link text_ at this time.
        - If you set a "Feed URL", then the sitemap CSS file will display an RSS
            icon that links to the "Feed URL" you define.
    - This plugin is defined in the `\Drupal\sitemap\Plugin\Sitemap\Frontpage`
        class.
- You can enable a section for each Menu defined on the site. Enabling a sitemap
    plugin for a menu will display an unordered list with the contents of the
    menu on the sitemap.
    - In "Plugin settings":
        - You can change the Title of the heading displayed before the unordered
            list of menu items (the heading will default to the menu title).
        - You can also display disabled menu items - but displaying disabled
            menu items has a very niche use case, and is **not recommended** for
            most sites!
    - If you want to change the order of the items in the list, the link text,
        the link destination; or you want to add or remove items in the list,
        then you must edit the menu links in the corresponding menu. To do so,
        first make sure that Drupal Core's "Menu UI" module (`menu_ui`) is
        enabled, then go to Administration -> Structure -> Menus, and click
        "Edit menu" next to the menu that you want to change.
    - Note that if you have a large number of menus, it may take a while to load
        the Sitemap configuration page.
    - These plugins are defined in the `\Drupal\sitemap\Plugin\Sitemap\Menu`
        class.
- If the "Book" module (`book`) is enabled (note the Book module is a contrib
    module in Drupal 11; but part of Drupal core in D10 and earlier), then you
    can enable a section for each Book defined on the site. Enabling a sitemap
    plugin for a book will display an unordered list with the pages in that book
    on the sitemap.
    - In "Plugin settings":
        - You can change the Title of the heading displayed before the unordered
            list of book pages (the heading will default to the book title).
        - You can also choose to expand the book outline to show pages at all
            levels of the book.
- If Drupal Core's "Taxonomy" module (`taxonomy`) is enabled, then you can
    enable a section for each Taxonomy Vocabulary defined on the site. Enabling
    a sitemap plugin for a vocabulary will display an unordered list with the
    terms in that vocabulary on the sitemap.
    - In "Plugin settings":
        - You can change the title of the heading displayed before the unordered
            list of terms (the heading will default to the vocabulary name).
        - You can choose whether or not to display the vocabulary description
            before the list of terms in that vocabulary.
        - You can choose whether or not to display the number of nodes tagged
            with a term.
        - You can also display unpublished taxonomy terms - but displaying
            disabled terms has a very niche use case, and is **not recommended**
            for most sites!
        - You can choose how many levels of taxonomy terms should be displayed.
        - You can choose a minimum threshold of nodes that must be tagged with a
            taxonomy term before it is displayed on the sitemap.
        - You can customize term links.
        - You can choose whether or not to display an RSS feed link of nodes
            tagged with that term. If you choose this, then the sitemap CSS file
            will display an RSS icon that links to the URL of the RSS feed for
            that taxonomy term.
    - If you want to change the order of the items in the list, the link text;
        or you want to add or remove items in the list, then you must edit the
        terms in the corresponding vocabulary. To do so, go to Administration ->
        Structure -> Taxonomy, and click "List terms" next to the vocabulary
        that you want to change.
    - Note that if you have a large number of vocabularies, it may take a while
       to load the Sitemap configuration page.
    - These plugins are defined in the
        `\Drupal\sitemap\Plugin\Sitemap\Vocabulary` class.
