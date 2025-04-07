# Using AI Agents in custom code
Whilst there exist a number of modules that allow you trigger AI Agents in
different ways, it is also possible to write your own custom code that will pass
input to an AI Agent and handle its output.

The process has four steps:

1. Load the AI Agent you require.
2. Provide the AI Agent with its configuration and other requirements.
3. Run the agent's `determineSolvability()` method.
4. Deal with the output appropriately.

## Load the AI Agent
The AI Agents are loaded through the use of a Drupal Plugin Manager, from their
unique IDs. In order to obtain the correct ID, you will need to have your own
process for deciding which plugin you wish to use: available IDs can be
discovered using the Plugin Manager:

```php

  $pluginIds = \Drupal::service('plugin.manager.ai_agents')->getDefinitions();

```

Once you have chosen the appropriate ID, you can then load it:

```php

  $plugin_id = 'YOUR CHOSEN AI AGENT PLUGIN ID';

  $definition = \Drupal::service('plugin.manager.ai_agents')->getDefinition($plugin_id);
  $aiAgent = \Drupal::service('plugin.manager.ai_agents')->createInstance($plugin_id, $definition);

```

## Set up the AI Agent
Now we need to give the loaded AI Agent all the information and services it will
require to carry out its task. As part of this process, the AI Agent requires a
configured [AI Provider](https://project.pages.drupalcode.org/ai/providers/matris/):
these instructions will not detail how to do this, as the [AI module's documentation](https://project.pages.drupalcode.org/ai/developers/base_calls/)
already covers this process. Once you have a loaded AI Provider, the process is
as follows:

```php

    // Create the task you require the AI Agent to perform.
    $task = new Task('Description of the task for the AI Agent to complete');
    $task->setComments(['Context description' => 'Additional context that the AI may require to carry out the task.']);
    
    // Set up the AI agent.
    $yourAgent->setTask($task);
    $yourAgent->setAiProvider($yourLoadedProvider);
    $yourAgent->setModelName('The Provider model for the AI Agent to use');
    $yourAgent->setAiConfiguration(['An array of any additional provider settings.']);
    
    // Instruct some AI Agents whether to create any entities they can
    // immediately, or to delay any direct action so additional steps can be
    // carried out.
    $yourAgent->setCreateDirectly(TRUE);

```

Once these steps have been completed, the AI Agent is ready to use. However, it
is also possible to set extra identifying information against the AI Agent's
communications with the AI in case you need to identify them later:

```php

  $yourAgent->setUserInterface('YOUR_MODULE_NAME', ['tags to identify the AI API call']);

```

## Identify the type of task
Identifying the AI Agent's task is simple:

```php

  $solution_type = $yourAgent->determineSolvability();

```

This will let the AI Agent decide the kind of task it is being asked to perform,
and return a constant from the AiAgentInterface that allows your custom code to
identify how it should proceed.

## Carry out further action
The result from the AI Agent's `determineSolvability()` method will be one of
the AiAgentInterface constants detailed in the [AI Agents plugins documentation](https://project.pages.drupalcode.org/ai_agents/developers/ai_agents_plugins#solutions),
and that documentation details the AIAgent methods that are expected to be run
for each of the responses. Be aware that not all AI Agent's will return every
possible response, as some are not relevant for certain kinds of tasks: your
code may vary from the example if you do not need to handle every possible
response.

```php

  switch ($solution_type) {
    case AiAgentInterface::JOB_NEEDS_ANSWERS:
      $response = $yourAgent->askQuestion();
      break;
      
    case AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION:
      $response = $yourAgent->answerQuestion();
      break;
      
    case AiAgentInterface::JOB_INFORMS:
      $response = $yourAgent->inform();
      break;
      
    case AiAgentInterface::JOB_SOLVABLE:
      $response = $yourAgent->solve();
      break;
      
    case AiAgentInterface::JOB_NOT_SOLVABLE:
      $response = NULL;
      break;
  
  }

```

Once your code receives its response, it will then need to decide what to do
next, such as display it to the user or run any additional information back
through the AI Agent. 