# Making AI Base Calls

## Idea

The idea behind the base calls of the AI module are quite simple. First we have an operation type, operation types are requests that we have grouped together because they are similar in fashion. For instance a chat request differs quite from a text-to-image call, thus they are different operation types.

Each of the operation types has to define one interface and two classes at minimum.

1. The operation type interface that decides how the call is done. The base call will have the same name in camelcase as the file. The base call has to take an abstracted input object as well as a raw input. Outside of that other methods can also be requested.
2. The abstracted input class is a way to abstract the input so, the provider knows what to expect, when that kind of input is given.
3. The abstracted output class is a way to abstract the output so, the provider knows where to put the and return the output.

Outside of this, providers also has configurations that comes with an unified method.

## Tagging

When you use any of the operation types, the third parameter is always an array of tags you can set. These are important for third party modules that uses the events, to be able to go in and read and potentially manipulate the requests or responses. Note that the provider and model is always set as tags.

The AI Logging module or the AI External Moderation example of modules using these events, where you can select based on tagging.

## The Operation Types and how to use them

* [Chat Call](call_chat.md)
* [Text-To-Image Call](call_text_to_image.md)
* [Text-To-Speech Call](call_text_to_speech.md)
* [Speech-To-Text Call](call_speech_to_text.md)
* [Embeddings Call](call_embeddings.md)
* [Moderation Call](call_moderation.md)
* [Audio-To-Audio Call](call_audio_to_audio.md)
* [Speech-To-Speech Call](call_speech_to_speech.md)
* [Image Classification Call](call_image_classification.md)
* [Text translations Call](call_translate_text.md)
