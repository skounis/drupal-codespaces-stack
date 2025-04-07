## Chat

Chat is probably the most used by far - this the "ChatGPT call" or usually how you interact with a LLM. So it takes a message conversation back and forth and returns the message from the service.

### Example normalized Chat call

The following is an example of sending the message "Hello" into OpenAI using the gpt-4o model.

```php
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$messages = new ChatInput([
  new ChatMessage('user', 'Hello!'),
]);
$provider =  \Drupal::service('ai.provider')->createInstance('openai');
// Set a system message.
$provider->setChatSystemRole('You are a really rude assistant that will not great people.')
/** @var \Drupal\ai\OperationType\Chat\ChatOutput $response */
$response = $provider->chat($messages, 'gpt-4o', ['my-custom-call']);
/** @var \Drupal\ai\OperationType\Chat\ChatMessage $return_message */
$return_message->getNormalized();
echo $return_message->getText();
// Returns something like "hmmmpf"
```

Note that if a model takes images, you can give that as the third parameter of the input.

### Chat Interfaces & Models

The following files defines the methods available when doing a chat call as well as the input and output.

* [ChatInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Chat/ChatInterface.php?ref_type=heads)
* [ChatInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Chat/ChatInput.php?ref_type=heads)
* [ChatOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Chat/ChatOutput.php?ref_type=heads)

### Setting system messages.
There is an abstracted way to set system messages for the providers that allows for it, the method is called `setChatSystemRole` and just takes the system role you want to set. Note that different providers weights these instructions more or less, so in certain cases it might make more sense to use two user messages instead.

### Streaming vs None-Streaming output.
There is a helper method when using the chat providers that makes it possible to stream the output, if the chat provider has the possibility to do so. This can be set via the method `$provider->streamedOutput(TRUE);`. This will give you back an iterator or generator that you can do a foreach on flush the output buffers each time. See the section in [Develop Third Party module](develop_third_party_module.md/#streaming-chat) about how to add checks for it.

### Chat Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Chat Generation Explorer` under `/admin/config/ai/explorers/chat-generation` to test out different calls and see the code that you need for it.

Here comes some examples of different type of levels you might want to integrate the module at. In these examples the code is loaded as a static, for best practice use Dependency Injection.
