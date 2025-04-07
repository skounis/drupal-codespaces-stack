<?php

namespace Drupal\eca;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\TokenInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorator that applies currently active logging settings.
 */
class ConfigurableLoggerChannel extends LoggerChannel {

  /**
   * The logger channel that is being decorated by this service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $loggerChannel;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $token = NULL;

  /**
   * The maximum allowed RFC log level.
   *
   * @var int
   */
  protected int $maximumLogLevel;

  /**
   * Collected log data for the current request.
   *
   * @var array
   */
  protected array $dataCurrentRequest = [];

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Indicates when logging is in progress to prevent recursions.
   *
   * @var bool
   */
  protected bool $alreadyLogging = FALSE;

  /**
   * The ConfigurableLoggerChannel constructor.
   *
   * @param string $channel_name
   *   The name of the logger channel that is being decorated.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The logger channel that is being decorated by this service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(string $channel_name, LoggerChannelInterface $loggerChannel, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($channel_name);
    $this->configFactory = $configFactory;
    $this->loggerChannel = $loggerChannel;
    $this->moduleHandler = $moduleHandler;
    $this->updateLogLevel((int) $configFactory->get('eca.settings')->get('log_level'));
  }

  /**
   * Get the token service.
   *
   * Note, we can NOT inject this service because that would lead to a
   * circular reference.
   *
   * @return \Drupal\eca\Token\TokenInterface
   *   The token service.
   */
  protected function token(): TokenInterface {
    if ($this->token === NULL) {
      // @codingStandardsIgnoreLine @phpstan-ignore-next-line
      $this->token = \Drupal::service('eca.service.token');
    }
    return $this->token;
  }

  /**
   * Set the ECA log level.
   *
   * @param int $level
   *   The RfcLogLevel:: level which should be configured for ECA.
   */
  public function updateLogLevel(int $level): void {
    $this->maximumLogLevel = $level;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    if ($this->alreadyLogging) {
      return;
    }
    $this->alreadyLogging = TRUE;
    if (is_string($level)) {
      // Convert to integer equivalent for consistency with RFC 5424.
      $level = $this->levelTranslation[$level];
    }
    if ($level <= $this->maximumLogLevel) {
      $tokens = [];
      if ($level === RfcLogLevel::DEBUG) {
        $token = $this->token();
        $data = $token->getTokenData();
        // Explicitly lookup for some often but ambiguously used token names
        // that a data provider only provides if explicitly requested.
        foreach (['entity', 'user', 'event', 'form'] as $ambiguous) {
          if (!isset($data[$ambiguous]) && $token->hasTokenData($ambiguous)) {
            $data[$ambiguous] = $token->getTokenData($ambiguous);
          }
        }
        if ($data) {
          $this->getTokenInfo($context, $tokens, $data, 'eca_token', 0);
          $message .= '<br>' . implode('<br>', $tokens);
        }
      }
      $this->loggerChannel->log($level, $message, $context);
    }
    $this->alreadyLogging = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStack(?RequestStack $requestStack = NULL): void {
    $this->loggerChannel->setRequestStack($requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentUser(?AccountInterface $current_user = NULL): void {
    $this->loggerChannel->setCurrentUser($current_user);
  }

  /**
   * {@inheritdoc}
   */
  public function setLoggers(array $loggers): void {
    $this->loggerChannel->setLoggers($loggers);
  }

  /**
   * {@inheritdoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0): void {
    $this->loggerChannel->addLogger($logger, $priority);
  }

  /**
   * Recursively prepare token info for the log display.
   *
   * @param array $context
   *   The array containing the variables for the watchdog.
   * @param array $tokens
   *   The list of lines to be added to the message.
   * @param array $data
   *   The token data to be analyzed.
   * @param string $prefix
   *   The prefix for variables in the context array.
   * @param int $level
   *   The level of recursion.
   */
  protected function getTokenInfo(array &$context, array &$tokens, array $data, string $prefix, int $level): void {
    $indent = str_repeat('&nbsp;&nbsp;', $level);
    foreach ($data as $key => $value) {
      $id = '%' . $prefix . '_' . $key;
      $tokens[] = $indent . '-&nbsp;' . $key . ' (' . $id . ')';
      if ($value instanceof EntityAdapter) {
        $value = $value->getEntity();
      }
      if ($value instanceof EntityInterface) {
        $info = 'Entity ';
        if ($value->getEntityTypeId() === $value->bundle()) {
          $info .= $value->getEntityTypeId();
        }
        else {
          $info .= $value->getEntityTypeId() . '/' . $value->bundle();
        }
        $info .= '/' . $value->id() . '/' . $value->label();
      }
      elseif ($value instanceof DataTransferObject) {
        $info = NULL;
        $level++;
        try {
          $properties = $value->getProperties(TRUE);
          $context[$id] = 'DTO';
        }
        catch (MissingDataException) {
          $properties = [];
          $context[$id] = 'DTO - properties not available';
        }
        if (empty($properties) && ($dto_string = $value->getString()) !== '') {
          $context[$id] .= ' "' . $dto_string . '"';
        }
        $this->getTokenInfo($context, $tokens, $properties, $prefix . '_' . $key, $level);
      }
      elseif (is_scalar($value)) {
        $info = gettype($value) . ' "' . (string) $value . '"';
      }
      elseif ($value instanceof PrimitiveInterface) {
        $info = gettype($value->getValue()) . ' "' . (string) $value->getValue() . '"';
      }
      elseif ($value instanceof TypedDataInterface) {
        $info = $value->getDataDefinition()->getDataType();
      }
      elseif ($value === NULL) {
        $info = 'NULL';
      }
      else {
        $info = get_class($value);
      }
      if ($info) {
        $context[$id] = $info;
      }
    }
  }

  /**
   * Returns the log data of the current request.
   *
   * @return array
   *   The log data.
   */
  public function getDataCurrentRequest(): array {
    return $this->dataCurrentRequest;
  }

}
