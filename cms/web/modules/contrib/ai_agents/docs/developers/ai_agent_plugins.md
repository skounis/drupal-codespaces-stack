# AI Agents plugins
The AI Agent plugins utilise [Drupal's Plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api) to
provide different actions that can be performed on a Drupal site by referencing
an external AI. The plugins allow these actions to be normalised so that
regardless of their actual functionality, they can be used in a standard fashion
and integrated with other modules cleanly.

## Adding a new plugin
New plugins can be implemented in any module by:
1. Adding a src/Plugin/AiAgent folder to the module.
2. Adding a new PHP plugin file that implements the AiAgentInterface.

As some of the AI Agent's functionality will be the same regardless of what the
plugin itself does, it is recommended to extend the AiAgentBase abstract class
to reduce duplication. However, as long as the new plugin conforms to the
requirements of the AiAgentInterface, this is not essential.

### Plugin identification
A new plugin must implement the `getId()` method to return a unique machine name,
and the `agentNames()` method to provide an array of agent names.

### Plugin capabilities
A new plugin must implement the `agentsCapabilities()` method to return a valid
array of information about the plugin's capabilities. The array must be keyed by
the `getId()` machine name, and provide meaningful name and description values
that can be presented to user's to explain the AI agent's purpose. The array
must also provide input and output keys that define the user input and the AI
Agent's output.

For examples on the format for this method, please see the existing plugins in
the module's src/Plugin/AiAgent folder.

### Plugin availability.
If your plugin should only be available under certain circumstances - for
example if a specific control or core module is enabled - you can specify that
in the `isAvailable()` method. If this method returns FALSE, the plugin will not
be available to use on the site: the base implementation returns TRUE by default
so any plugin not overriding this method will be usable by default.

If the `isAvailable()` method returns FALSE, it is recommended to override the
`isNotAvailableMessage()` method to return a meaningful message about why the
plugin is unavailable.

In addition to this, if the plugin should only be available to certain users to
use, the `hasAccess()` method should be overridden to perform any required checks.
Each plugin has the current user available in `$this->currentUser`. The parent
method will check if this user has the roles set against the AI Agent in its
settings (or if the user is the [root account](https://www.drupal.org/docs/user_guide/en/user-admin-account.html)):
because of this, it is recommended that any override of the `hasAccess()` method
calls the parent method first, unless this specifically conflicts with your
requirements.

The `hasAccess()` method must return a valid AccessResult entity of the
appropriate type, based on the calculation done within the method. A Neutral
return will result in access being forbidden.

### Plugin functionality
#### AI Agent Prompts
Within the AI Agent plugin, the `$this->agentHelper->runSubAgent()` method is
available to manage communication with an LLM or other AI in a structured
fashion. This method takes the name of a prompt file and an array of any 
relevant content in the format `['CONTEXT DESCRIPTION' => 'CONTEXT VALUE'']`.
The file name **MUST** correspond to the name of a file in the plugin provider
module's codebase, in a folder `/prompts/<plugin machine name>/<prompt file name>.<prompt file extension>`.
The file extension must NOT be included in the name passed to the `runSubAgent()`
method. Currently only .yml file prompt inputs and JSON format outputs from the
AI are supported, so you must include appropriate instructions about the outputs
in your prompts.

For the correct structure to use for te prompt files, please see this module's
own prompt files in `prompts`. **Please note**: as the AI Agents are intended to
alter the configuration and content of a production site, **it is very important
that prompts are written and tested by developers with a good understanding of
writing prompts for AI**. 

#### The determineSolvability method
When a plugin is triggered, it will first run the `determineSolvability()` method.
This identify what kind of task the user is trying to carry out, either through 
code or by passing the request to the LLM to identify. The method MUST return
one of the task type constants defined in the AiAgentInterface: this result will
then determine which of the AIAgents methods will be called to carry out the
task.

#### Solutions
Based on the result from the `determineSolvability()` method, the AI Agent will
run one of a number of possible functions. Only the methods that relate to task
type constants returned by your implementation need to be added to your
codebase: if your `determineSolvability()` method only ever returns 
AiAgentInterface::JOB_NOT_SOLVABLE, none of the possible follow-up methods will
ever be triggered.

1. **JOB_NOT_SOLVABLE**: if this constant is returned, the
   user will be advised that the task cannot be carried out, and possibly asked
   to rephrase. This should be the default return if the code does not identify
   the task as belonging to any more suitable category.
2. **JOB_SOLVABLE**: if this is returned, the AI Agent's `solve()` method will be
   triggered. It is intended that this method will perform some content or
   configuration change on the site, using code within the method: for example,
   creating a new Node Type or amending an existing Field.
3. **JOB_NEEDS_ANSWERS**: if this is returned, the `askQuestion()` method is run.
   This is intended to be run where the AI Agent requires additional information
   from the user to complete the task it has been asked to perform. In most
   cases, the default implementation of this in AiAgentBase will be sufficient:
   this returns the AI's questions unedited and displays them to the user for a
   response. Any response will be a new submission to the AI, but the previous
   questions and responses will be sent as context.
4. **JOB_SHOULD_ANSWER_QUESTION**: if this is returned, the AI Agent's 
   `answerQuestion()` method is run. This is intended for use when the AI Agent
   requires more information from the AI before it can complete its task. It
   will most likely use the `$this->agentHelper->runSubAgent()` method to send
   additional instructions and context to the AI before the response is then run
   again through `determineSolvability()`.
5. **JOB_INFORMS**: if this is returned, the `inform()` method will be run. This
   is intended to return relevant information to the user in response to a
   question that does not require any changes being made to the site.

#### Error handling
Exceptions thrown within the AI Agent will be caught and their messages
displayed to the user: please be cautious running code that may throw its own 
Exceptions, and ensure any error messages you set are user facing.

In the event of an error, the AI Agent's `rollback()` method will be run. It is
intended that this will undo any actions that have been carried out by the AI
Agent up to the point. The AiAgentBase implementation will use the stored record
of changed configuration and revert changes to them: if your AI Agent has 
non-configuration actions it needs to undo, you will need to override this
method. **Please refrain from reverting configuration changes globally** as
sites may have unrelated changes that you would be getting rid of.

### Plugin configuration
Any available plugin will appear to users with the correct access in the global
configuration form at `/admin/config/ai/agents/settings`. It will have the 
standard configuration options mentioned in [the module's documentation](https://project.pages.drupalcode.org/ai_agents#Default_AI_Agent_configuration)
available by default.

If your plugin implements the `buildConfigurationForm()` method, the output of
this form will be available on the default settings page. If you implement this
method, you **MUST** also implement the `submitConfigurationForm()` method to
store the additional settings as plugin configuration.

## Testing your AI Agent
To assist with testing your AI Agent, the [AI Agent Explorer module](https://project.pages.drupalcode.org/ai_agents/modules/ai_agents_explorer)
has been created to allow you to send requests directly to the AI Agent and view
debugging information on your screen. For more information, please see the
documentation.