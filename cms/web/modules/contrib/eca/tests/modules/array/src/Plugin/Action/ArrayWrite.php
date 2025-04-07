<?php

namespace Drupal\eca_test_array\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_test_array\Event\ArrayEvents;
use Drupal\eca_test_array\Event\ArrayWriteEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * An action that writes into a static array.
 *
 * @Action(
 *   id = "eca_test_array_write",
 *   label = @Translation("Static array: write"),
 *   description = @Translation("This action writes into a static array."),
 *   nodocs = true
 * )
 */
class ArrayWrite extends ConfigurableActionBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The static array.
   *
   * @var array
   */
  public static array $array = [];

  /**
   * Whether to restrict access for anonymous users. Default is FALSE.
   *
   * @var bool
   */
  public static $restrictAccess = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'key' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['key'],
      '#title' => $this->t('Key'),
      '#weight' => 10,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['value'],
      '#title' => $this->t('Value'),
      '#weight' => 20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('key') === 'TEST_FOR_INVALID_KEY') {
      $form_state->setErrorByName('key', 'Triggered validation error for key.');
    }
    if ($form_state->getValue('value') === 'TEST_FOR_INVALID_VALUE') {
      $form_state->setErrorByName('value', 'Triggered validation error for value.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    $result = AccessResult::allowedIf(!static::$restrictAccess || (bool) $account->id());
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $key = $this->configuration['key'];
    $value = $this->tokenService->replace($this->configuration['value']);
    static::$array[$key] = $value;
    $this->eventDispatcher->dispatch(new ArrayWriteEvent($key, $value), ArrayEvents::WRITE);
  }

}
