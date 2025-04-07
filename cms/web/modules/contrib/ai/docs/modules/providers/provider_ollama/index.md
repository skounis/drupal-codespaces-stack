# Ollama Provider
## What Is Ollama Provider?
The Ollama Provider module allows the AI Core module to connect with an Ollama
LLM to provide its functionality. For more information about Ollama, please see
[their website](https://ollama.com/).

## Enabling and configuration
1. Enable the module as usual.
2. Visit /admin/config/ai/providers/ollama and enter the connection details for
   your Ollama LLM.
3. The Provider will then be available for the AI module to use; visit
   /admin/config/ai/settings to select it as a default provider for your chosen actions.

## Setting up Ollama
To avoid duplication and out of date information, we recommend using the
documentation on [Ollama's github pages](https://github.com/ollama/ollama) to
get started with Ollama. Ollama also provides [a Docker image](https://hub.docker.com/r/ollama/ollama), which can be used
in a DDEV local development environment.