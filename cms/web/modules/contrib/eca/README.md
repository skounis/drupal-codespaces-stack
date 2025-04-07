**ECA is the no-code solution that empowers you to orchestrate your Drupal
site.**

ECA is a powerful, versatile, and user-friendly rules engine for Drupal. The
core module is a processor that validates and executes event-condition-action
plugins. Integrated with the graphical user interface
[BPMN.iO](https://www.drupal.org/project/bpmn_io), ECA is a robust system for building
conditionally triggered action sets.

### How it works

ECA gets triggered for every Drupal event. It validates these events against
event-condition-action models and processes all the models for the current
event. Like Drupal Rules, ECA leverages existing components of Drupal core, i.e.
events and actions. It comes with its own plugin manager for conditions, which
results in all three components (events, conditions, actions) being available as
plugins that can easily be extended by other modules. ECA models are stored in
config, so they can be imported and exported via the admin UI or Drush.

<div class="note-version">

#### ECA Guide

To learn all about ECA, and it's growing ecosystem, please visit the
[ECA Guide](https://ecaguide.org)
which provides a range of useful content:

- Explaining ECA and how it works
- Describing the main ECA concepts and how you can use them for your models
- Documentation of modellers and all plugins
- A library with downloadable ECA models to learn from
- Video tutorials

There is more to come all the time. So, it's always worth checking back.

</div>

### User interface

ECA Core is a processing engine that runs in the background. It needs an
integrated modeller - a front-end tool with which you define
event-condition-action models (a.k.a. rules). ECA provides a plugin manager with
an interface to easily integrate existing tools that already provide that
capability. And if the modeller supports templates for events, conditions and
actions, ECA will provide them for all the plugins that are available on the
current Drupal site.
#### Modellers

[BPMN.iO](https://www.drupal.org/project/bpmn_io) is the recommended ECA
modeller. It's a JavaScript-based implementation for building models as
two-dimensional diagrams, integrated into the Drupal admin UI.

Alternatively, you can use these modellers. Please do not use them unless you
have no other option:

- [Camunda](https://www.drupal.org/project/camunda):
  User-friendly desktop client for designing and deploying automated processes
- [ECA Classic Modeller](https://www.drupal.org/project/eca_cm):
  "Low-level" modelling tool using Drupal core's form API

#### Important: You will need to install the modeller separately

You will find instructions on how to install and use the modeller you select
over at their respective project pages and in the documentation. On production
sites, however, you can run ECA models without having any modeller being
available or enabled.

### Features

- Plugin managers for modellers, events, and conditions
- Interfaces and abstract base classes and traits
- Integration of all actions from the Drupal core Actions module and all
  available contrib modules
- Extensive context stack support (optional with
  [Context Stack](https://www.drupal.org/project/context_stack))
- Support for caching, loops, logging, states, tokens, etc.
- Prevents recursions
- TypedData support
- Tagging for event characterization
- Models are stored in config, so they can be imported and exported via Drush or
  the configuration management UI

### Included sub-modules

Installing ECA Core has no dependencies other than Drupal Core! Additional
functionality with extra events, conditions and actions can be tailored to the
needs of each Drupal site. Currently, these sub-modules are packaged with ECA
and can be enabled individually:

- **ECA Access:** Events and actions to control access on entities and fields
- **ECA Base:** Base events, conditions and actions
- **ECA Cache:** Actions to read, write or invalidate cache items
- **ECA Config:** Config events
- **ECA Content:** Content entity events, conditions and actions
- **ECA Endpoint - since 1.1.0:** Events to define your own endpoint/routes on
  the fly and actions to interact with their requests and responses
- **ECA File:** File system related actions
- **ECA Form:** Form API events, conditions and actions
- **ECA Language:** Language and translation events and actions
- **ECA Log:** Events and actions for Drupal log messages
- **ECA Migrate:** Migrate events
- **ECA Misc:** Miscellaneous events and conditions from Drupal core and the
  kernel
- **ECA Queue:** Events, conditions and actions for queued operations.
- **ECA Render - since 1.1.0:** Events and actions to work with Drupal's render
  API for blocks, views and all around themes and Twig
- **ECA User:** User events, conditions and actions
- **ECA Views:** Execute and export Views query results within ECA
- **ECA Workflow:** Content entity workflow actions

The sub-modules extend ECA with plugins for events, conditions and actions. In
addition, there is the sub-module **ECA UI** which gives you access to the ECA
admin interface and **ECA Development** which brings a couple of Drush commands
for ECA developers.

### Installation

It is easy to get started with ECA:

- Download the ECA module and a modeller, BPMN.iO is recommended
- Install ECA, the sub-modules you want to use, and the modeller

For a Quick Start, see the [Install section in the ECA Guide](https://ecaguide.org/eca/install).

### More integrations

The maintainers are interested to help maintainers of other Drupal modules to
integrate their projects with ECA by providing either event, condition or action
plugins - or all three of them. In
[this issue](https://www.drupal.org/project/eca/issues/3222620)
we maintain a list of modules, where discussion or even work has already been
started and where you can follow its progress.

#### Modules integrating with ECA

- Modellers:
  - [BPMN.iO](https://www.drupal.org/project/bpmn_io)
  - [Camunda](https://www.drupal.org/project/camunda)
  - [ECA Classic Modeller](https://www.drupal.org/project/eca_cm)
- ECA integrations with other modules:
  - [ECA Commerce](https://www.drupal.org/project/eca_commerce)
  - [ECA Content Access](https://www.drupal.org/project/eca_content_access)
  - [ECA Context](https://www.drupal.org/project/eca_context)
  - [ECA Entity Print](https://www.drupal.org/project/eca_entity_print)
  - [ECA Entity Share](https://www.drupal.org/project/eca_entity_share)
  - [ECA Flag](https://www.drupal.org/project/eca_flag)
  - [ECA Helper](https://www.drupal.org/project/eca_helper)
  - [ECA Maestro](https://www.drupal.org/project/eca_maestro)
  - [ECA Metatag](https://www.drupal.org/project/eca_metatag)
  - [ECA Mustache](https://www.drupal.org/project/eca_mustache)
  - [ECA Parameters](https://www.drupal.org/project/eca_parameters)
  - [ECA Site Building Tools](https://www.drupal.org/project/eca_site_building)
  - [ECA State Machine](https://www.drupal.org/project/eca_state_machine)
  - [ECA Tamper](https://www.drupal.org/project/eca_tamper)
  - [ECA Tour](https://www.drupal.org/project/eca_tour)
  - [ECA Twilio](https://www.drupal.org/project/eca_twilio_action)
  - [ECA Variety Pack](https://www.drupal.org/project/eca_variety_pack)
  - [ECA VBO](https://www.drupal.org/project/eca_vbo)
  - [ECA View data export](https://www.drupal.org/project/eca_views_data_export)
  - [ECA Webform](https://www.drupal.org/project/eca_webform)
  - [ECA Webprofiler](https://www.drupal.org/project/eca_webprofiler)
- Other modules:
  - [AI](https://www.drupal.org/project/ai)
  - [Augmentor AI](https://www.drupal.org/project/augmentor)
  - [Bookable Calendar](https://www.drupal.org/project/bookable_calendar)
  - [CrowdSec](https://www.drupal.org/project/crowdsec)
  - [DANSE](https://www.drupal.org/project/danse)
  - [DiscordPHP](https://www.drupal.org/project/discord_php)
  - [Drupal Remote Dashboard](https://www.drupal.org/project/drd)
  - [Easy Email](https://www.drupal.org/project/easy_email)
  - [GitLab API](https://www.drupal.org/project/gitlab_api)
  - [Group Actions](https://www.drupal.org/project/group_action)
  - [HTTP Client Manager](https://www.drupal.org/project/http_client_manager)
  - [HTTP Client Manager Issuu Oembed](https://www.drupal.org/project/http_client_manager_issuu_oembed)
  - [IOC: Internet Of Contributors](https://www.drupal.org/project/ioc)
  - [Message Notify ECA](https://www.drupal.org/project/message_notify_eca)
  - [Prompt AI](https://www.drupal.org/project/prompt)
  - [Push Framework](https://www.drupal.org/project/push_framework)
  - [Shelly](https://www.drupal.org/project/shelly)
  - [Solcast](https://www.drupal.org/project/solcast)
  - [Token ECA Alter](https://www.drupal.org/project/token_eca_alter)
  - [Workflow ECA](https://www.drupal.org/project/workflow_eca)

### Requirements

- Drupal 10+ (ECA 2 requires Drupal 10.3 or 11)
- PHP 8.1+

<div class="note-version">

#### Documentation

Please follow the links in the right column, especially the documentation link
which gets you to the
[ECA Guide](https://ecaguide.org).
And if you want to help out, please get in touch. Contributors are very welcome,
and there is a lot to do.

And here are some links to further information:

- [Blog: ECA for Drupal: Successful launch, moving on](https://www.lakedrops.com/en/blog/eca-drupal-successful-launch-moving)
- [Blog: Drupal ECA integrates bpmn.io](https://bpmn.io/blog/posts/2022-drupal-eca-integration.html)
- [Blog: ECA rules engine for Drupal: RC1 released](https://www.lakedrops.com/en/blog/eca-rules-engine-drupal-rc1-released)
- [Blog: State of ECA: What's new in Drupal's new rules engine with beta-2](https://www.lakedrops.com/en/blog/state-eca-whats-new-drupals-new-rules-engine-beta-2)
- [Blog: Event Condition Action - Business Process Modelling in Drupal 9+](https://www.lakedrops.com/en/blog/post/event-condition-action-business-process-modeling-drupal-9)
- [Slides ECA RC1 (June 2022)](https://www.lakedrops.com/en/slides/event-condition-action-rc1)
- [Video Part 1](https://www.lakedrops.com/en/video/eca-intro-part-1)
- [Video Part 2](https://www.lakedrops.com/en/video/eca-intro-part-2)
- [Video from SFDUG presentation](https://www.youtube.com/watch?v=h9oXGTa1D0I)
- [Video from NWDUG presentation](https://www.youtube.com/watch?v=b512Lk1PSSk)

</div>

### Join the team

Not only are developers needed, we have so much more that needs to be addressed.
Here is a list but even that may not be complete:

- Development
  - ECA and plugins
  - Optimization of the integrated BPMN.iO client
- Writing tests
- Review and feedback
- Support (Issue queue and in chats)
- Documentation
- Translations
- Descriptions on the drupal.org project pages
- Spread the word

Please get in touch by opening an issue in the issue queue, sending the
maintainers a message on their drupal.org profile or head over to
[Drupal Slack #ECA channel](https://drupal.slack.com/archives/C0287U62CSG).

#### Credits:

ECA Logo by [Nico Grienauer](https://www.drupal.org/u/grienauer)
