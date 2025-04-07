<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Simple XML Sitemap logger.
 */
class Logger {

  use StringTranslationTrait;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger.
   *
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The actual message.
   *
   * @var string
   */
  protected $message = '';

  /**
   * The actual substitutions.
   *
   * @var array
   */
  protected $substitutions = [];

  /**
   * Logger constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    LoggerInterface $logger,
    MessengerInterface $messenger,
    AccountProxyInterface $current_user,
  ) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  /**
   * Sets the message with substitutions.
   *
   * @param string $message
   *   Message to set.
   * @param array $substitutions
   *   Substitutions to set.
   *
   * @return $this
   */
  public function m(string $message, array $substitutions = []): static {
    $this->message = $message;
    $this->substitutions = $substitutions;

    return $this;
  }

  /**
   * Logs with an arbitrary level.
   *
   * @param string $logSeverityLevel
   *   The severity level.
   *
   * @return $this
   */
  public function log(string $logSeverityLevel = LogLevel::NOTICE): static {
    $this->logger->$logSeverityLevel(strtr($this->message, $this->substitutions));

    return $this;
  }

  /**
   * Logs an exception.
   *
   * @param \Throwable $exception
   *   The exception.
   *
   * @param string $logSeverityLevel
   *   The severity level.
   *
   * @return $this
   */
  public function logException(\Throwable $exception, string $logSeverityLevel = LogLevel::ERROR): static {
    $message = $this->message !== '' ? strtr($this->message, $this->substitutions) : Error::DEFAULT_ERROR_MESSAGE;
    Error::logException($this->logger, $exception, $message, [], $logSeverityLevel);

    return $this;
  }

  /**
   * Displays the message given the right permission.
   *
   * @param string $displayMessageType
   *   The message's type.
   * @param string $permission
   *   The permission to check for.
   *
   * @return $this
   */
  public function display(string $displayMessageType = MessengerInterface::TYPE_STATUS, string $permission = ''): static {
    if (empty($permission) || $this->currentUser->hasPermission($permission)) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $this->messenger->addMessage($this->t($this->message, $this->substitutions), $displayMessageType);
    }

    return $this;
  }

}
