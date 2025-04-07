<?php

namespace Drupal\eca_test_array\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * An action that increments a number in an array.
 *
 * @Action(
 *   id = "eca_test_array_increment",
 *   label = @Translation("Static array: increment"),
 *   description = @Translation("This action increments a number in a static array."),
 *   nodocs = true
 * )
 */
class ArrayIncrement extends ConfigurableActionBase {

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
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('key') === 'TEST_FOR_INVALID_KEY') {
      $form_state->setErrorByName('key', 'Triggered validation error for key.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $key = $this->configuration['key'];
    static::$array[$key] = isset(static::$array[$key]) ? static::$array[$key] + 1 : 1;
  }

}
