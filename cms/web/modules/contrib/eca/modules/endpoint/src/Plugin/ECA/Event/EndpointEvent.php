<?php

namespace Drupal\eca_endpoint\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_endpoint\EndpointEvents;
use Drupal\eca_endpoint\Event\EndpointAccessEvent;
use Drupal\eca_endpoint\Event\EndpointEventBase;
use Drupal\eca_endpoint\Event\EndpointResponseEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of ECA endpoint events.
 *
 * @EcaEvent(
 *   id = "eca_endpoint",
 *   deriver = "Drupal\eca_endpoint\Plugin\ECA\Event\EndpointEventDeriver",
 *   eca_version_introduced = "1.1.0"
 * )
 */
class EndpointEvent extends EventBase {

  /**
   * The endpoint base path.
   *
   * @var string
   */
  protected string $endpointBasePath;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->endpointBasePath = $container->getParameter('eca_endpoint.base_path');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $definitions = [];
    $definitions['response'] = [
      'label' => 'ECA Endpoint response',
      'event_name' => EndpointEvents::RESPONSE,
      'event_class' => EndpointResponseEvent::class,
      'tags' => Tag::RUNTIME,
    ];
    $definitions['access'] = [
      'label' => 'ECA Endpoint access',
      'event_name' => EndpointEvents::ACCESS,
      'event_class' => EndpointAccessEvent::class,
      'tags' => Tag::RUNTIME | Tag::BEFORE,
    ];
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'first_path_argument' => '',
      'second_path_argument' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['first_path_argument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First path argument'),
      '#default_value' => $this->configuration['first_path_argument'],
      '#description' => $this->t('The <strong>first</strong> path argument to match up. This argument will be resolved from the URL path <em>/eca/<strong>{first}</strong>/{second}</em>.'),
      '#required' => TRUE,
      '#weight' => 10,
    ];
    $form['second_path_argument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second path argument'),
      '#default_value' => $this->configuration['second_path_argument'],
      '#description' => $this->t('Optionally specify a second path argument to match up. This argument will be resolved from the URL path <em>/eca/{first}/<strong>{second}</strong></em>.'),
      '#required' => FALSE,
      '#weight' => 20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['first_path_argument'] = $form_state->getValue('first_path_argument');
    $this->configuration['second_path_argument'] = $form_state->getValue('second_path_argument');
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    switch ($this->getDerivativeId()) {

      case 'response':
      case 'access':
        $configuration = $ecaEvent->getConfiguration();
        $first_path_argument = trim((string) ($configuration['first_path_argument'] ?? ''));
        $second_path_argument = trim((string) ($configuration['second_path_argument'] ?? ''));
        $wildcard = '';
        $wildcard .= $first_path_argument === '' ? '*' : $first_path_argument;
        $wildcard .= '::';
        $wildcard .= $second_path_argument === '' ? '*' : $second_path_argument;
        return $wildcard;

      default:
        return parent::generateWildcard($eca_config_id, $ecaEvent);

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event instanceof EndpointEventBase) {
      [$first_path_argument, $second_path_argument] = explode('::', $wildcard, 2);
      if (($first_path_argument !== '*') && (reset($event->pathArguments) !== $first_path_argument)) {
        return FALSE;
      }
      if (($second_path_argument !== '*') && (next($event->pathArguments) !== $second_path_argument)) {
        return FALSE;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      EndpointAccessEvent::class,
    ],
    properties: [
      new Token(name: 'arguments', description: 'The arguments of the request path.'),
      new Token(name: 'uid', description: 'The ID of the user account of the request.'),
    ],
  )]
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      EndpointResponseEvent::class,
    ],
    properties: [
      new Token(name: 'path_arguments', description: 'The arguments of the request path.'),
      new Token(name: 'request', description: 'The request.', properties: [
        new Token(name: 'method', description: 'The request method, e.g. "GET" or "POST".'),
        new Token(name: 'path', description: 'The requested path.'),
        new Token(name: 'query', description: 'The query arguments of the request.'),
        new Token(name: 'headers', description: 'The request headers.'),
        new Token(name: 'content_type', description: 'The content type of the request.'),
        new Token(name: 'content', description: 'The content of the POST request.'),
        new Token(name: 'ip', description: 'The client IP.'),
      ]),
      new Token(name: 'uid', description: 'The ID of the user account of the request.'),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];

    if ($event instanceof EndpointAccessEvent) {
      $data += [
        'arguments' => $event->pathArguments,
        'uid' => $event->account->id(),
      ];
    }
    elseif ($event instanceof EndpointResponseEvent) {
      $data += [
        'path_arguments' => $event->pathArguments,
        'request' => [
          'method' => $event->request->getMethod(),
          'path' => $event->request->getPathInfo(),
          'query' => $event->request->query->all(),
          'headers' => $event->request->headers->all(),
          'content_type' => $event->request->getContentTypeFormat(),
          'content' => (string) $event->request->getContent(),
          'ip' => $event->request->getClientIp(),
        ],
        'uid' => $event->account->id(),
      ];
    }

    $data += parent::buildEventData();
    return $data;
  }

}
