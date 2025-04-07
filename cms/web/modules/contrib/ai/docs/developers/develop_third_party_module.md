# Develop a third party module

When you want to develop a third party module using the available [calls](base_calls.md) in the AI module, here are some tips and pointers.

## First check so a provider exists for you operation type.

If you want your third party module to use chat for instance and there is no chat provider installed, you have to have some graceful fallback where you tell that to the user.

If you load the AI Provider Plugin service (`ai.provider`), you can use the method `hasProvidersForOperationType` that takes the operation type data name and a boolean if it has to be setup (working API Key, connection etc.). This means that your module could do something like this.

```php
if (!\Drupal::service('ai_provider')->hasProvidersForOperationType('chat', TRUE)) {
  return [
    '#type' => 'markup',
    '#markup' => $this->t('Sorry, no provider exists for Chat, install one first'),
  ];
}
```

## Default model for operation type

For each operation type the end user can set an default provider and model. There are also a couple of pseudo operation types, that are based on capabilities + operation type, like Chat with Image Vision (chat_with_image_vision) and Chat with Complex JSON (chat_with_complex_json).

If you load the AI Provider Plugin service (`ai.provider`), you can use the method `getDefaultProviderForOperationType` that takes the operation type data name and gives back an array if it exists, so you can write something like this:

```php
$defaults = \Drupal::service('ai_provider')->getDefaultProviderForOperationType('chat');
if (empty($defaults['provider_id']) || empty($default['model_id'])) {
  // We have to do something else, since there is no manual provider.
}
```

## Making simple provider/model selection available

If you want the end user to be able to choose a model in a settings page, but you do not care about the actual configuration of the provider or model, there are three helper functions in the AI Provider Plugin service.

One is the method `getSimpleProviderModelOptions` that takes the operation type data name and some optional methods.

You can take this value and save in your config and then use this value with the method `loadProviderFromSimpleOption` and `getModelNameFromSimpleOption`, that both takes the value as parameters.

This means that you can have a fairly complex form that looks something like this.

Your config file:
```php

$form['provider_model'] = [
  '#type' => 'select',
  '#title' => $this->t('Custom Model'),
  '#description' => $this->t('Choose a provider/model if you are not using the default model'),
  '#default_value' => $config->get('provider_model'),
  '#options' => \Drupal::service('ai_provider')->getSimpleProviderModelOptions('chat'),
];
```

And then have a file where you use the config, something like this.

```php
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$ai_provider = \Drupal::service('ai_provider');
$provider_model = $ai_provider->\Drupal::service('ai_provider');
// If not set, try to load default.
if (!$provider_model) {
  $default = $ai_provider->getDefaultProviderForOperationType('chat');
  // If no default we fail and give some error message.
  if (empty($default['provider_id'])) {
    throw new \Exception('No model set');
  }
  // Load the provider
  $provider = $ai_provider->createInstance($default['provider_id']);
  $model = $default['model_id'];
}
else {
  $provider = $ai_provider->loadProviderFromSimpleOption($provider_model);
  $model = $ai_provider->getModelNameFromSimpleOption($provider_model);
}

// Send your chat message.
$messages = new ChatInput([
  new ChatMessage('user', 'Hello!'),
]);
$provider->chat($input, $model, ['my-custom-module']);

```

## Making advanced provider/model selection available (experimental)

If you want more complex forms, where the models and configuration are loaded via ajax after you choose the provider, we have a more complex form helper.

This one is not set in stone yet and will most likely change before going into production, so use with care.

It goes a little bit like this.

In you config form:

```php

use Drupal\ai\Service\AiProviderFormHelper;

public buildForm($form, $form_state) {
  $form_helper = \Drupal::service('ai.form_helper');
  $form_helper->generateAiProvidersForm($form, $form_state, 'chat', 'some_prefix', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
}

public validateForm($form, $form_state) {
  $form_helper = \Drupal::service('ai.form_helper');
  $form_helper->validateAiProvidersConfig($form, $form_state, 'chat', 'some_prefix');
}

public submitForm($form, $form_state) {
  $form_helper = \Drupal::service('ai.form_helper');
  $config->set('provider', $form_state->get('some_prefix_ai_provider'));
  $config->set('model', $form_state->get('some_prefix_ai_model'))
  $config->set('configuration', $form_state->get($form_helper->generateAiProvidersConfigurationFromForm($form, $form_state, 'chat', 'some_prefix')));
}

```

## Listening for provider changes.
Sometimes when you have your own provider picker, you might need to listen for events where this provider is disabled/uninstalled. This can be done
via the [Provider Disabled event](events.md)

### Setting Chat system messages.
There is an abstracted way to set system messages for the providers that allows for it, the method is called `setChatSystemRole` and just takes the system role you want to set. Note that different providers weights these instructions more or less, so in certain cases it might make more sense to use two user messages instead.

So it would be something like this:

```php
$provider =  \Drupal::service('ai.provider')->createInstance('openai');
// Set a system message.
$provider->setChatSystemRole('You are an expert at bananas.')
```

## Streaming Chat

There is a way to output the chat as a stream, meaning that it outputs the words as they come in. For your third party provider to support it, you need to first turn this on (or have it as a config) via the method `$provider->streamedOutput(TRUE);`.

When you have turned this on, its important to note that not all providers support this, so you have to add a check and respond correctly depending on how it works. This is an example of how to do this.

```php
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

$response = $ai_provider->chat($some_messages, 'model')->getNormalized();
// If its streaming.
if (is_object($response) && $response instanceof StreamedChatMessageIteratorInterface) {
  // Streamed response.
  return new StreamedResponse(function () use ($response) {
    // Iterate the response.
    foreach ($response as $message) {
      // Echo and flush.
      echo $message->getText();
      ob_flush();
      flush();
    }
    // Make sure not to cache.
  }, 200, [
    'Cache-Control' => 'no-cache, must-revalidate',
    'Content-Type' => 'text/event-stream',
    'X-Accel-Buffering' => 'no',
  ]);
}
// Otherwise non-streaming.
else {
  // Return a normal response.
  return new Response($response->getText());
}
```

Note that you might have to have javascript that handles this as well and also a server that does not buffer whole inputs, but can send them out chunked.

