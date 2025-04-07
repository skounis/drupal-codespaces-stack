CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Typical Usage
* Maintainers


INTRODUCTION
------------

This module provides the necessary support for scheduling transitions of content
within a Content Moderation workflow. When publishing or unpublishing content,
Scheduler will use this sub-module if the content is controlled by a moderation
workflow, and will use the standard publish/unpublish actions if not. If you
want to use Scheduler and Content Moderation together then this module is
required.

 * For a full description of the module, visit the [SCMI project page](https://www.drupal.org/project/scheduler_content_moderation_integration).

 * To submit bug reports and feature suggestions use the [project issue queue](https://www.drupal.org/project/issues/scheduler_content_moderation_integration).


REQUIREMENTS
------------

This module requires the following modules:

* [Scheduler](https://www.drupal.org/project/scheduler)

Scheduling requires a proper [cron configuration](https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks)
in order to function properly.


INSTALLATION
------------

* Install as you would normally [install a contributed Drupal module](https://www.drupal.org/node/1897420).


CONFIGURATION
-------------

* Add the relevant entity to a workflow: Configuration » Workflow »
  Workflows » Edit {Your Workflow} » "This Workflow Applies to".
* Enable "scheduled publishing" and/or "scheduled unpublishing" and review other
  Scheduler configuration options: Structure » Entity Types » {Your Type} »
  Edit » Scheduler.

This module provides no standalone configuration page or additional permissions.


TYPICAL USAGE
-------------

When scheduling is enabled on an entity with a Workflow, "Publish state" and
"Unpublish state" fields are added to the "Scheduling Options" fieldset/tab in
the entity edit form.

In a typical "Draft-Published-Archived" workflow, content editors may want
to draft a content change before it going live. To do this, the editor would:

* Edit the content and make the appropriate changes.
* Set "Change to:" to "Draft"
* Expand the "Scheduling Options" if necessary
  * Set "Publish on" to the desired date and time.
  * Set "Publish state" to "Published".


MAINTAINERS
-----------

[//]: # cspell:disable
* [Christian Fritsch (chr.fritsch)](https://www.drupal.org/u/chrfritsch)
* [Daniel Bosen (daniel.bosen)](https://www.drupal.org/u/danielbosen)
* [Volker Killesreiter (volkerk)](https://www.drupal.org/u/volkerk)
* [Stephen Mustgrave (smustgrave)](https://www.drupal.org/u/smustgrave)
* [Jonathan Smith (jonathan1055)](https://www.drupal.org/u/jonathan1055)
[//]: # cspell:enable

This project was initially developed with support from [Thunder](https://www.drupal.org/thunder).
