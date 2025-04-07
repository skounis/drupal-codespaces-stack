# Create a Scheduler plugin for an entity type

Here are step-by-step instructions for creating a plugin for a new entity type. The following naming conventions are used:

- `{entity_type_id}` refers to the actual entity type id, the lower-case machine name, for example ‘node’, ‘media’ and ‘commerce_product’.
- `{Type}` refers to the equivalent UpperCamelCase non-underscore version used in class names, for example ‘Node’, ‘Media’ and ‘CommerceProduct’.

## Steps

1. Create the new plugin definition file, named `/src/Plugin/Scheduler/{Type}Scheduler.php`
1. The complete list of plugin definition properties, with examples, is as follows:
```
     * @SchedulerPlugin(
     *  id = "example_scheduler",
     *  label = @Translation("Example Scheduler Plugin"),
     *  description = @Translation("Support for scheduling Example entities"),
     *  entityType = "example",
     *  dependency = "example_product",
     *  typeFieldName = "category",
     *  develGenerateForm = "devel_generate_form_example",
     *  collectionRoute = "entity.example.overview"
     *  userViewRoute = "view.scheduler_scheduled_example.user_page",
     *  schedulerEventClass = "\Drupal\scheduler\Event\SchedulerExampleEvents",
     *  publishAction = "example_publish",
     *  unpublishAction = "example_unpublish"
     * )
```
1. Keep the properties in the order shown above. The first five properties are mandatory and the remaining seven are optional. See [src/SchedulerPluginBase.php](https://git.drupalcode.org/project/scheduler/-/blob/2.x/src/SchedulerPluginBase.php) and [src/Annotation/SchedulerPlugin.php](https://git.drupalcode.org/project/scheduler/-/blob/2.x/src/Annotation/SchedulerPlugin.php) for detailed explanations.
1. Create a new file `src/Event/Scheduler{Type}Events` using a copy of the media file `src/Event/SchedulerMediaEvents.php` as a basis. Do not use the node file as this has the legacy non-generic event naming convention.
1. Add extra two lines in `config/schema/scheduler.schema.yml` to use the saved alias for the new third party settings. If this is not done we get “Configuration inspector - The site's configuration does not match the associated schema” error on the status report. Do not add a new comment line before the reuse of the alias, as oddly this causes a parse error.
1. Create a new view definition and save it as `config/optional/views.view.scheduler_scheduled_{entity_type_id}.yml`. Note, this should be in `config/optional` because only the node content view is stored in `config/install`.
1. A main admin view is not mandatory but is highly desirable, and the view url should be an extension of the entity collection url, for example admin/content/media/scheduled. A user view variant is optional, but if created, the url should be user/{uid}/scheduled_{entity_type_id}
1. In `scheduler.install` add a hook_update function to load the new view.
1. Add the required local task tab definition in `src/Plugin/Derivative/DynamicLocalTasks.php`. The new route should be `view.scheduler_scheduled_{entity_type_id}.overview`. If there is no tab for the general view then add it. This was the case for Media and Commerce Products, but there is a [Core issue](https://www.drupal.org/project/drupal/issues/3199682) to create them automatically.
1. Scheduler Rules Integration - create `scheduler_rules_integration/src/Event/Rules{Type}Event.php` copying from the Media version of this class. In the `CONST` definitions use the entity type id, including underscore if necessary.
1. Update `README.md` to include the new entity in the list of implementations.
