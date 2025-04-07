## Introduction

The Trash module works by adding an internal field to all trash-enabled entity types called `deleted`. This field is a Unix timestamp indicating when an individual entity was soft-deleted (put in the trash). That's really the only difference you would see if just looking at your entities after installing the module, though the `deleted` field isn't actually exposed in any visible way on the entity itself.
When pressing "Delete" on an entity form, that field gets set with the current time, that's it.

If the field is empty, the entity is not trashed. If it has a timestamp, it is trashed. If it has a timestamp, it may be actually deleted (aka "purged") for real after some configurable delay.

## How does a "trashed" entity disappear from everywhere (except the "Trashcan") if it isn't actually deleted yet?

Trash module uses the entity access hook, alters Entity Queries, Views queries and the "entity loading" process to achieve this.

The access hook implementation allows the module to actively stop/forbid someone from directly accessing a trashed entity without the appropriate permissions.

The Entity Query and Views query alters achieve the same for any listings, such as in Views or the default list builders.

As these are the same for all content entities, the module should work the same with all of them.

## How does Trash achieve this?

First, entities must not actually be deleted when pressing "Delete" - or even via `$entity->delete()`. Annoyingly, there is no great hook or event to implement which can ensure that operation can always be intercepted. However, it is fairly easy to substitute an entity's storage implementation. Basically, Trash implements `hook_entity_type_alter()`, dynamically generates/renders a new storage class for your enabled entity, and sets that on your entity instead. Say what? Yes, you read that correctly. It uses Twig to render out a new class, which inherits from the original class it found from the entity definition, saves it as a PHP file and uses that instead, much like how templates are replaced with classes. This is also why it's [at least for now] limited to SQL based entity storage classes.

That trait which gets included has all the logic to override the `delete()` call, and instead just set the `$entity->deleted` timestamp. (It does do a bit more if what was deleted was a translation instead of the whole entity, but that's not important right now.)

This way, there is no way to accidentally bypass the trashcan even by other modules deleting stuff and having no idea Trash exists.

## What about "inside" the trashcan itself, or if I really want to delete (purge) something right now?

Trash module has the concept of a "trash context" (yeah, there are many things named context already, this is none of those). It's more or less just a static string variable value in the module's `TrashManager` service.

The context is global and can have one of three values (hello enums!), and the current value affects the outcome of certain things:

- `active`
  - The normal operation mode. Trash intercepts deletions, and alters queries to hide them.
- `inactive`
  - You're actively working with trashed content. Deleting an entity will cause it to be permanently deleted (purged). Trash will still alter queries to hide deleted entities.
- `ignore`
    - Trash module is told to explicitly do nothing. Queries are not altered.

A note on query alteration: If you have made a custom Entity Query and add a condition on the `deleted` field yourself then Trash will try to detect that, and if found it'll back off and trust you know what you're doing.

Trash also uses a route option to automatically switch the context to one of these values for certain routes to allow trashed entities to be viewed or permanently deleted.

## How will this affect my custom code?

In the broad view, it should not affect it much at all. Obviously, if you have a custom entity storage class, you should check that it's compatible with the code Trash generates.

Trash also overrides the access handler class for nodes, so you may want to ensure any custom class you use either does the same things or extend Trash module's class.
