<?php

namespace Drupal\eca\Plugin\ECA\Condition;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eca\EcaState;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for ECA provided conditions.
 */
abstract class ConditionBase extends PluginBase implements ConditionInterface, ContainerFactoryPluginInterface {

  use ContextAwarePluginTrait;
  use PluginFormTrait;

  /**
   * The triggered event.
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
   * The entity type and bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * Symfony request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The ECA-related token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * User account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The time service.
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
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, RequestStack $request_stack, TokenInterface $token_service, AccountProxyInterface $current_user, TimeInterface $time, EcaState $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->requestStack = $request_stack;
    $this->tokenService = $token_service;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->state = $state;
    $this->setConfiguration($configuration);
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
      $container->get('entity_type.bundle.info'),
      $container->get('request_stack'),
      $container->get('eca.token_services'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('eca.state')
    );
  }

  /**
   * Returns the named value from context, if available.
   *
   * @param string $name
   *   The name of the value that should be returned.
   *
   * @return mixed|null
   *   The named value, if available. NULL otherwise.
   */
  public function getValueFromContext(string $name): mixed {
    try {
      return $this->getContextValue($name);
    }
    catch (ContextException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): ConditionInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEvent(object $event): ConditionInterface {
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
  public function isNegated(): bool {
    return $this->configuration['negate'] ?? FALSE;
  }

  /**
   * Reverse the boolean result, if plugin configuration has negation turned on.
   *
   * @param bool $result
   *   Boolean result before optional negation.
   *
   * @return bool
   *   The real result after negation settings has been checked and applied.
   */
  protected function negationCheck(bool $result): bool {
    return $this->isNegated() ? !$result : $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $i = 1;
    $form['negate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate the condition'),
      '#default_value' => $this->configuration['negate'],
      '#description' => $this->t('Negates the condition. Makes TRUE to FALSE and vice versa.'),
      '#weight' => $i,
    ];
    /** @var \Drupal\Core\Plugin\Context\ContextDefinition $definition */
    foreach ($this->getPluginDefinition()['context_definitions'] ?? [] as $key => $definition) {
      $i++;
      $form[$key] = [
        '#type' => 'textfield',
        '#title' => $definition->getLabel(),
        '#default_value' => $this->configuration[$key],
        '#description' => $this->t('Provide the token name of the %key that this condition should operate with.', [
          '%key' => $key,
        ]),
        '#weight' => $i,
        '#eca_token_reference' => TRUE,
      ];
    }
    return $this->updateConfigurationForm($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['negate'] = (bool) $form_state->getValue('negate', FALSE);
    foreach ($this->getPluginDefinition()['context_definitions'] ?? [] as $key => $definition) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
    if ($form_state->hasValue('context_mapping')) {
      $this->setContextMapping($form_state->getValue('context_mapping'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): ConditionBase {
    $this->configuration = $configuration + $this->defaultConfiguration();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $values = [
      'negate' => FALSE,
    ];
    foreach ($this->getPluginDefinition()['context_definitions'] ?? [] as $key => $definition) {
      $values[$key] = '';
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [];
  }

}
