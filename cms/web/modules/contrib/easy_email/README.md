Easy Email is an HTML email system designed to be as simple as possible to configure,
with a robust set of features.

## Requirements

This module requires one of the following HTML mailer modules:

* [Drupal Symfony Mailer Lite](https://www.drupal.org/project/symfony_mailer_lite)
* [Drupal Symfony Mailer](https://www.drupal.org/project/symfony_mailer)

Either of these modules can be used, and you only need one of them.

## How Is Easy Email Different From Other Modules?

There are a lot of HTML email contrib modules, and some of them are functionally
similar to Easy Email. But, they all require a significant amount of work to configure.
Many of them also require an in-depth knowledge of how Drupal and email mime
processing work. Easy Email tries to take care of all that for you out of the box
with sensible defaults, so you can just enable the module, create a template, and
then start sending HTML emails without a lot of other configuration. That's the
dream, anyway.

## Features

Easy Email templates currently support the following features:

* Token replacement for all fields.
* To, CC, and BCC recipients.
* Sender name, address, and reply to address.
* HTML body with optional “Inbox Preview” text that is designed to be hidden in the email body, but visible in the inbox preview on supported email clients.
* Optional plain text body field, which can entered manually or generated from the HTML body field.
Dynamic attachments field: use tokens or relative paths to specify attachments.
* Email log contains a list of all sent emails, with a full view of what was sent.
* Logged emails are automatically linked to the user accounts they were sent to, making it easy to find all the emails you sent a particular user. (which they may claim they did not receive.😜)
* Entity-based architecture means you can use standard lifecycle hooks to alter or extend email processing: hook_entity_presave(), hook_entity_update(), etc.
* Easy Email templates are fieldable, and it’s expected that you will need to add custom fields for most use cases. Example: You have an email template for a Drupal Commerce order, you will need an entity reference field for an order, and then you can use order-derived tokens to address the email to the owner of a specific order.

## Future Features

On the roadmap for Easy Email:

* Disable email logging per template. For some sites, it may be too much data to store every email’s content in the database, so make the storage of sent emails optional.
* Purge email log: Add the ability to save only emails in the log newer than XX days to help manage the growth of the data.
* Inline images: This is already supported by Symfony Mailer but we need to make it easy to add them to templates.
