# Scheduler Plugin System

The 2.x branch of Scheduler introduced a plugin system to allow scheduled processing of more entity types. Plugins have been developed to schedule Content, Media, Commerce Products and Taxonomy Terms, and these plugins are included in the project.

Other implementations of the plugin are being developed in 3rd-party modules, and these can be stored in that 3rd-party module's codebase. If an implementation is developed for another core entity type, or a commonly-used module, then these may be added to Scheduler in future.

The primary requirement is that the entity type has to have the concept of "being published" which is boolean true/false. This can be any field, it does not need to be the core `status` field. The field just needs to implement the method `->setPublished(bool)`.

The entity type also needs to have a "bundle" field, but there is an open issue for [supporting non-bundled entities](https://www.drupal.org/project/scheduler/issues/3355087).
