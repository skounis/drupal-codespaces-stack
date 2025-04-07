<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase as CoreActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eca\EcaState;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for ECA provided actions.
 */
abstract class ActionBase extends CoreActionBase implements ContainerFactoryPluginInterface, ActionInterface {

  /**
   * The ID of the containing ECA model.
   *
   * @var string
   */
  protected string $ecaModelId;

  /**
   * The ID of the action within the ECA model.
   *
   * @var string
   */
  protected string $actionId;

  /**
   * Triggered event leading to this action.
   *
   * @var \Symfony\Contracts\EventDispatcher\Event
   */
  protected Event $event;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The ECA-related token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * ECA state service.
   *
   * @var \Drupal\eca\EcaState
   */
  protected EcaState $state;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TokenInterface $token_service, AccountProxyInterface $current_user, TimeInterface $time, EcaState $state, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenService = $token_service;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->state = $state;
    $this->logger = $logger;

    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('eca.token_services'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('eca.state'),
      $container->get('logger.channel.eca')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function externallyAvailable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setEcaActionIds(string $ecaModelId, string $actionId): ActionInterface {
    $this->ecaModelId = $ecaModelId;
    $this->actionId = $actionId;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEvent(object $event): ActionInterface {
    $this->event = $event;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvent(): Event {
    return $this->event;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function handleExceptions(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function logExceptions(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
