# AI Agents Explorer
This module allows AI Agents to be triggered directly from a UI, with debug
information displayed for each step of this AI Agent's progress. This module is
intended to assist with debugging issues with AI Agents and is not intended to
be used on a production website.

## Dependencies
1. The AI (Core) module, with at least one configured AI Provided module.
2. The AI Agents module.

## Using the module
When you have installed the module, visit the AI Agents setting page at
`/admin/config/ai/agents`. Each agent will have an additional "Explore"
operation added to it. If you click the link, you will be taken to an Explorer
form which will allow you to select an AI Provider, configure it and send a
request directly to your chosen AI Agent. The debug information will appear on
the screen, allowing you to identify if a problem is with an AI Agent or the
code using it.