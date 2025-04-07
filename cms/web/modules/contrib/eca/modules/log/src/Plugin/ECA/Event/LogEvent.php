<?php

namespace Drupal\eca_log\Plugin\ECA\Event;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_log\Event\LogMessageEvent;
use Drupal\eca_log\LogEvents;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA Events for log messages.
 *
 * @EcaEvent(
 *   id = "log",
 *   deriver = "Drupal\eca_log\Plugin\ECA\Event\LogEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class LogEvent extends EventBase {

  /**
   * An instance holding log data accessible as token.
   *
   * @var \Drupal\eca\Plugin\DataType\DataTransferObject|null
   */
  protected ?DataTransferObject $logData = NULL;

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $actions = [];
    $actions['log_message'] = [
      'label' => 'Log message created',
      'event_name' => LogEvents::MESSAGE,
      'event_class' => LogMessageEvent::class,
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if ($this->eventClass() === LogMessageEvent::class) {
      $values = [
        'channel' => '',
        'min_severity' => '',
      ];
    }
    else {
      $values = [];
    }
    return $values + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($this->eventClass() === LogMessageEvent::class) {
      $form['channel'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type'),
        '#description' => $this->t('The name of the logger type.'),
        '#default_value' => $this->configuration['channel'],
      ];
      $form['min_severity'] = [
        '#type' => 'select',
        '#title' => $this->t('Minimum severity'),
        '#description' => $this->t('The minimum severity. E.g. "critical" also covers "alert" and below.'),
        '#options' => RfcLogLevel::getLevels(),
        '#default_value' => $this->configuration['min_severity'],
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if ($this->eventClass() === LogMessageEvent::class) {
      $this->configuration['channel'] = $form_state->getValue('channel');
      $this->configuration['min_severity'] = $form_state->getValue('min_severity');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();
    $channel = isset($configuration['channel']) ? mb_strtolower(trim((string) $configuration['channel'])) : '';
    if ($channel === '') {
      $channel = '*';
    }
    $min_severity = isset($configuration['min_severity']) ? mb_strtolower(trim((string) $configuration['min_severity'])) : '';
    if ($min_severity === '') {
      $min_severity = '*';
    }
    return $channel . '::' . $min_severity;
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    /** @var \Drupal\eca_log\Event\LogMessageEvent $event */
    [$channel, $min_severity] = explode('::', $wildcard);
    if ($channel !== '*' && $event->getContext()['channel'] !== $channel) {
      return FALSE;
    }
    if ($min_severity !== '*' && $event->getSeverity() > (int) $min_severity) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'log',
    description: 'The logger data which dispatched the event.',
    classes: [
      LogMessageEvent::class,
    ],
    properties: [
      new Token(name: 'severity', description: 'The log message severity.'),
      new Token(name: 'message', description: 'The log message.', properties: [
        new Token(name: 'raw', description: 'The raw log message.'),
        new Token(name: 'full', description: 'The full and formatted log message with all variables replaced.'),
      ]),
      new Token(name: 'context', description: 'All context variables of the log message.'),
    ],
  )]
  public function getData(string $key): mixed {
    $event = $this->event;
    if ($key === 'log' && $event instanceof LogMessageEvent) {
      if ($this->logData === NULL) {
        $message = str_replace('@backtrace_string', '', $event->getMessage());
        $context = $this->cleanupIterableForDto($event->getContext());
        $this->logData = DataTransferObject::create([
          'severity' => DataTransferObject::create($event->getSeverity()),
          'message' => [
            'raw' => DataTransferObject::create($event->getMessage()),
            'full' => DataTransferObject::create(trim(PlainTextOutput::renderFromHtml(new FormattableMarkup($message, $event->getContext())))),
          ],
          'context' => DataTransferObject::create($context),
        ]);
      }
      return $this->logData;
    }
    return parent::getData($key);
  }

  /**
   * Helper function to cleanup log context for DTO.
   *
   * @param iterable $values
   *   The iterable value.
   *
   * @return array
   *   The cleaned array.
   */
  private function cleanupIterableForDto(iterable $values): array {
    $cleanValues = [];
    foreach ($values as $key => $value) {
      if ($value instanceof TypedDataInterface ||
        $value instanceof EntityInterface ||
        $value instanceof MarkupInterface ||
        is_scalar($value)
      ) {
        $cleanValues[$key] = $value;
      }
      elseif (is_iterable($value)) {
        $cleanValues[$key] = $this->cleanupIterableForDto($value);
      }
    }
    return $cleanValues;
  }

}
