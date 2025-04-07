# Add test coverage for a Scheduler plugin

Here are step-by-step instructions for adding test coverage for a new entity type. The following naming conventions are used:

- `{entity_type_id}` refers to the actual entity type id, the lower-case machine name, for example ‘node’, ‘media’ and ‘commerce_product’.
- `{Type}` refers to the equivalent UpperCamelCase non-underscore version used in class names, for example ‘Node’, ‘Media’ and ‘CommerceProduct’.

When adding new array values or case statement sections, if possible add the new values at the end of the existing values or after the current lines.

1. Before changing any test files, run all tests to make sure that any changes to common functions have not created new errors. All tests should pass because the new plugin will not be loaded due the provider module not being installed.
1. The first change is in `tests/src/Functional/SchedulerBrowserTestBase.php` to add the new dependency module into the `$modules` array, and if this is not a core module then also add it to the `"require-dev"` list in [composer.json](https://git.drupalcode.org/project/scheduler/-/blob/2.x/composer.json). After this addition there will probably be test failures, even without explicitly testing the new entity type.
1. In `tests/modules/scheduler_api_test/scheduler_api_test.module` add two use statements for the `entity\{Type}` and `{Type}Interface`.
1. In function `_scheduler_api_test_get_entities()` add a new case for new entity type id. This is done first because it is needed even when the new entity type is not tested explicitly.
1. In `tests/src/Traits` add a new setup trait for the entity type, basing it on `SchedulerMediaSetupTrait.php`. As a start, you can do a case-sensitive replacement of all ‘media’ with the value of `{entity_type_id}` and ‘Media’ with the value of `{Type}`.
1. In `tests/src/Traits/SchedulerSetupTrait.php` add the new entity type to the array in function `dataStandardEntityTypes()` and add the new non-scheduler entity type into `dataNonEnabledTypes()` - these providers are used in 19 tests, so will automatically provide a reasonable coverage of the new plugin.
1. Add a new case section in `createEntity()`, `getEntityByTitle()`, `titleField()` and `entityAddUrl()`. Expand the array of types in `entityTypeObject()`
1. In `SchedulerBrowserTestBase` add a use statement for the new trait and use it at the top of the trait. Execute the trait setUp() function from inside the base setUp().
1. Make the same changes in `tests/src/FunctionalJavascript/SchedulerJavascriptTestBase.php`
1. Make a new test `tests/src/Functional/SchedulerBasic{Type}Test.php` - use a copy of SchedulerBasicMediaTest as a starting point.
1. In `tests/modules/scheduler_extras/scheduler_extras.module` function `scheduler_extras_form_alter()` include the form id for adding the new entity type.
