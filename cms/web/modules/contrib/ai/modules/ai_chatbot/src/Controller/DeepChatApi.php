<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_chatbot\Service\MessagesButtons;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Returns responses for AI Deepchat routes.
 */
class DeepChatApi extends ControllerBase {

  /**
   * All buttons available for the assistant.
   *
   * @var array
   */
  protected array $buttons = [];

  /**
   * Should show structured results.
   *
   * @var bool
   */
  protected bool $showStructuredResults = FALSE;

  /**
   * Constructs a new DeepChatApi object.
   *
   * @param \Drupal\ai_assistant_api\AiAssistantApiRunner $aiAssistantClient
   *   The AI Assistant API client.
   * @param \Drupal\ai_chatbot\Service\MessagesButtons $messagesButtons
   *   The messages buttons render service.
   */
  public function __construct(
    protected AiAssistantApiRunner $aiAssistantClient,
    protected MessagesButtons $messagesButtons,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('ai_assistant_api.runner'),
      $container->get('ai_chatbot.buttons'),
    );
  }

  /**
   * Handles the DeepChat API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
   *   The JSON or Streamed response.
   */
  public function api(Request $request): JsonResponse|StreamedResponse {
    // Get the request content and decode it.
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    // Retrieve the assistant_id from request payload.
    if (!isset($data['assistant_id'])) {
      // Assistant ID is required.
      return new JsonResponse(['error' => t('assistant_id is required.')], 422);
    }

    // Load the AiAssistant entity.
    $assistant = $this->entityTypeManager()->getStorage('ai_assistant')->load($data['assistant_id']);

    if (!$assistant instanceof AiAssistant) {
      return new JsonResponse(['error' => t('Invalid assistant ID.')], 422);
    }

    // Set the assistant in the AiAssistantApiRunner.
    $this->aiAssistantClient->setAssistant($assistant);

    if (isset($data['stream']) && $data['stream'] === 1) {
      $this->aiAssistantClient->streamedOutput(TRUE);
    }

    // Optionally, set the thread_id if provided.
    if (isset($data['thread_id'])) {
      $this->aiAssistantClient->setThreadsKey($data['thread_id']);
    }

    // Set the context if provided.
    if (isset($data['context']) && is_array($data['context'])) {
      $this->aiAssistantClient->setContext($data['context']);
    }

    // Check if 'messages' array is provided.
    if (isset($data['messages']) && is_array($data['messages'])) {
      $messages = $data['messages'];

      // Extract user messages.
      $conversation = [];
      foreach ($messages as $message) {
        if (isset($message['role'], $message['text'])) {
          $role = $message['role'];
          $text = $message['text'];
          if ($role === 'user') {
            $conversation[] = new UserMessage($text);
          }
        }
      }

      if (empty($conversation)) {
        return new JsonResponse(['error' => t('No user messages provided.')], 400);
      }

      // Set the latest user message.
      $latestUserMessage = end($conversation);
      $this->aiAssistantClient->setUserMessage($latestUserMessage);
      $this->aiAssistantClient->setThrowException(TRUE);

      // Set the structured result data.
      $this->showStructuredResults = isset($data['structured_results']) && $data['structured_results'];

      // Set the copy button if wanted.
      if (isset($data['show_copy_icon']) && $data['show_copy_icon']) {
        $this->buttons[] = [
          'svg' => $this->moduleHandler()->getModule('ai_chatbot')->getPath() . '/assets/copy-icon.svg',
          'weight' => 1,
          'class' => ['copy'],
          'alt' => $this->t('Copy message'),
          'title' => $this->t('Copy message'),
        ];
      }

      // Process the user's message.
      try {
        $response = $this->aiAssistantClient->process();

        // Handle the response, which could be a ChatMessage or Stream.
        $normalizedResponse = $response->getNormalized();

        // Decide response type based on the request.
        if ($normalizedResponse instanceof ChatMessage) {
          return $this->createResponse($normalizedResponse);
        }
        else {
          return $this->createStreamedResponse($normalizedResponse);
        }
      }
      catch (\Exception $e) {
        $error_message = $assistant->get('error_message');

        // Try to find a specific error message to use.
        if ($e instanceof AiBadRequestException) {
          $error_message = $assistant->get('specific_error_messages')['AiBadRequestException'] ?? $error_message;
        }
        elseif ($e instanceof AiRateLimitException) {
          $error_message = $assistant->get('specific_error_messages')['AiRateLimitException'] ?? $error_message;
        }
        elseif ($e instanceof AiQuotaException) {
          $error_message = $assistant->get('specific_error_messages')['AiQuotaException'] ?? $error_message;
        }
        elseif ($e instanceof AiSetupFailureException) {
          $error_message = $assistant->get('specific_error_messages')['AiSetupFailureException'] ?? $error_message;
        }
        elseif ($e instanceof AiRequestErrorException) {
          $error_message = $assistant->get('specific_error_messages')['AiRequestErrorException'] ?? $error_message;
        }

        // Otherwise use the default error message.
        return new JsonResponse([
          'error' => $error_message,
        ], 500);

      }
    }
    else {
      // No messages provided in the request.
      return new JsonResponse(['error' => t('No messages provided.')], 400);
    }
  }

  /**
   * Returns a normal response.
   */
  public function createResponse($normalizedResponse): JsonResponse {
    $assistantResponseText = $normalizedResponse->getText();
    // Set the assistant message for logging or further processing.
    $this->aiAssistantClient->setAssistantMessage($assistantResponseText);
    // Change the response if needed.
    $extra = $this->moduleHandler()->invokeAll('deepchat_prepend_message', [
      $assistantResponseText,
      'text',
      $this->aiAssistantClient->getAssistant()->id(),
      $this->aiAssistantClient->getThreadsKey(),
    ]);

    $converter = $this->getCommonMarkConverter();
    $assistantResponseText = $this->rewriteMarkdownMessage($assistantResponseText);
    $assistantResponseText = $converter ? $converter->convert($assistantResponseText) : $assistantResponseText;

    $assistantResponseText .= $this->renderStructuredResults();
    if (!empty($extra)) {
      foreach ($extra as $message) {
        $assistantResponseText .= $message;
      }
    }

    $assistantResponseText .= $this->messagesButtons->getRenderedButtons($this->buttons, $this->aiAssistantClient->getAssistant()->id(), $this->aiAssistantClient->getThreadsKey());

    // Default to JSON response.
    return new JsonResponse([
      'html' => $assistantResponseText,
    ]);
  }

  /**
   * Creates a StreamedResponse for the assistant's reply.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $streamedMessages
   *   The text generated by the AI assistant.
   *   The thread identifier.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   The streamed response.
   */
  private function createStreamedResponse(StreamedChatMessageIterator $streamedMessages): StreamedResponse {
    $response = new StreamedResponse();

    // Set headers for streaming.
    $response->headers->set('Content-Type', 'text/event-stream');
    $response->headers->set('Cache-Control', 'no-cache');
    $response->headers->set('Connection', 'keep-alive');

    $response->setCallback(function () use ($streamedMessages) {
      $saveMessage = '';

      // Disable PHP output buffering.
      while (ob_get_level() > 0) {
        ob_end_flush();
      }

      // Ensure implicit flush is enabled.
      ini_set('implicit_flush', '1');
      ob_start();
      // Make sure to start session.
      $this->aiAssistantClient->startSession();
      foreach ($streamedMessages as $chunk) {
        $saveMessage .= $chunk->getText();
        // Send each chunk.
        $this->createSseMessage($saveMessage, TRUE);
        ob_flush();
      }

      $this->aiAssistantClient->setAssistantMessage($saveMessage);
      ob_flush();

      $extra = $this->moduleHandler()->invokeAll('deepchat_prepend_message', [
        $saveMessage,
        'text',
        $this->aiAssistantClient->getAssistant()->id(),
        $this->aiAssistantClient->getThreadsKey(),
      ]);
      if (!empty($extra)) {
        foreach ($extra as $message) {
          $this->createSseMessage($message);
        }
      }
      // Send the structured results.
      $this->createSseMessage($this->renderStructuredResults());
      // Send the buttons.
      $this->createSseMessage($this->messagesButtons->getRenderedButtons($this->buttons, $this->aiAssistantClient->getAssistant()->id(), $this->aiAssistantClient->getThreadsKey()));
      // Check if the output buffer is empty.
      while (ob_get_level() > 0) {
        ob_end_flush();
      }
    });

    return $response;
  }

  /**
   * Rewrite markdown message.
   *
   * @param string $message
   *   The message to rewrite.
   *
   * @return string
   *   The rewritten message.
   */
  public function rewriteMarkdownMessage(string $message): string {
    // Find any link from a markdown message an iterate them.
    $pattern = '/\[(.*?)\]\((.*?)\)/';
    preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $link = trim($match[2]);
      $text = $match[1];
      // If the link does not start with a protocol or slash, add the base URL.
      if (strpos($link, '://') === FALSE && strpos($link, '/') !== 0) {
        $link = base_path() . $link;
      }
      // Sometimes it adds double slashes at the start.
      if (substr($link, 0, 2) === '//') {
        $link = substr($link, 1);
      }

      $message = str_replace($match[0], "[$text]($link)", $message);
    }

    return $message;
  }

  /**
   * Send SSE message.
   *
   * @param string $message
   *   The message to send.
   * @param bool $is_chunk
   *   If the message is a chunk.
   * @param string $type
   *   The type of message.
   */
  public function createSseMessage(string $message, bool $is_chunk = FALSE, string $type = 'html') {
    $message = $this->rewriteMarkdownMessage($message);
    $converter = $this->getCommonMarkConverter();
    if ($is_chunk) {
      // Send the chunk.
      $html = $converter ? $converter->convert($message)->__toString() : $message;
      if ($html) {
        echo 'data: ' . Json::encode([$type => $html, 'overwrite' => TRUE]) . "\n\n";
        flush();
      }
    }
    else {
      echo 'data: ' . Json::encode([$type => $message]) . "\n\n";
      flush();
    }
  }

  /**
   * Get the structured results if wanted.
   *
   * @return string
   *   The structured results.
   */
  public function renderStructuredResults(): string {
    $results = '';
    if ($this->showStructuredResults) {
      $structured = $this->aiAssistantClient->getStructuredResults();
      if ($structured) {
        // Add the button.
        $this->buttons[] = [
          'svg' => $this->moduleHandler()->getModule('ai_chatbot')->getPath() . '/assets/combine-left-right-icon.svg',
          'weight' => 1,
          'class' => ['structured-results'],
          'alt' => $this->t('Structured results'),
          'title' => $this->t('Structured results'),
        ];
        $results .= '<div class="structured-results-dump"><pre>' . Yaml::dump($structured, 10) . "</pre></div>";
      }
    }
    return $results;
  }

  /**
   * Gets the common mark converter if available.
   *
   * @return \League\CommonMark\CommonMarkConverter|null
   *   The common mark converter.
   */
  public function getCommonMarkConverter() {
    $converter = NULL;
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      // Ignore the non-use statement loading since this dependency may not
      // exist.
      // @codingStandardsIgnoreLine
      $converter = new \League\CommonMark\CommonMarkConverter();
    }
    return $converter;
  }

}
