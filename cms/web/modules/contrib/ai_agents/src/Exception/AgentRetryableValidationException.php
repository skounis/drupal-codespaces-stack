<?php

namespace Drupal\ai_agents\Exception;

/**
 * Thrown when an LLM response fails validation but can be retried.
 */
class AgentRetryableValidationException extends \Exception {

  /**
   * Construct the exception. Note: The message is NOT binary safe.
   *
   * @link https://php.net/manual/en/exception.construct.php
   *
   * @param string $message
   *   [optional] The Exception message to throw.
   * @param int $code
   *   [optional] The Exception code.
   * @param null|\Throwable $previous
   *   [optional] The previous throwable used for the exception chaining.
   * @param string $prompt
   *   [optional] Additional prompts for LLMs to explain the error.
   */
  public function __construct(
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = NULL,
    protected string $prompt = '',
  ) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Helper to set the prompt.
   *
   * @param string $prompt
   *   A prompt for an LLM to explain the error.
   */
  public function setPrompt(string $prompt): void {
    $this->prompt = $prompt;
  }

  /**
   * Get the prompt value.
   *
   * @return string
   *   The prompt string for the LLM.
   */
  public function getPrompt(): string {
    return $this->prompt;
  }

}
