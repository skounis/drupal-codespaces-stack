# AI Agents
The AI Agents module provides a number of plugins capable of sending information
about a Drupal site to a chosen AI provider and carry out specific actions with
the returned response. The module also provides a number of sub-modules to
integrate the AI Agents with common Drupal entities.

## Dependencies
1. The AI Core module, with at least one AI Provider to be installed and
   configured

## Recommended modules
This module on its own only provides a small number of AI Agents and the
infrastructure to manage them using custom code. In order to integrate the AI
Agents without custom code, you will need to enable at least one module that
integrates the AI Agents into Drupal. This module provides a number of
sub-modules to do this, or the AI (Core) dependency also provides the [AI
Assistant API](https://project.pages.drupalcode.org/ai/modules/ai_assistant_api/) and [AI Chatbot](https://project.pages.drupalcode.org/ai/modules/ai_assistant_api/#ai-chatbot-module) modules, which provide a user interface for
allowing users to trigger configured agents through text interactions with an
LLM.

## Getting started
Once enabled, the AI Module plugins can be configured at `/admin/config/ai/agents`.
All available AI Agents will appear in the list, with a brief description of
what they can be used for. Each agent also provides a link where its default
settings can be customised.

### Default AI Agent configuration
Each AI Agent has default prompts pre-configured so that they can be used
without any additional configuration. The default prompts are stored for
information in the folder `prompts` in the codebase, with each stored in a
sub-folder with the specific plugin's machine name. Each AI Plugin can perform
a number of actions that determine the eventual output: these will be different
for each AI Agent depending on what it does and how it does it.

The prompts used can be extended through the AI Agent's configuration form, by
adding additional prompt information or config. Alternatively, you can
completely rewrite the prompt by clicking its "override" link. **Once the prompt
has been overridden, the file in the codebase will NOT be used**.

## Using the AI Agents
Please refer to the instructions for the module you have chosen for utilising
the AI Agents, or else refer to the developer guide for assistance using the AI
Agents in your own custom code.