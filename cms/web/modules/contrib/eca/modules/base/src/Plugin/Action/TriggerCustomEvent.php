<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\CustomEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Trigger a custom event.
 *
 * @Action(
 *   id = "eca_trigger_custom_event",
 *   label = @Translation("Trigger a custom event"),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class TriggerCustomEvent extends ConfigurableActionBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

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
  public static function externallyAvailable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $event_id = $this->tokenService->replaceClear($this->configuration['event_id']);
    $event = new CustomEvent($event_id, ['event' => $this->event]);
    $event->addTokenNamesFromString($this->configuration['tokens']);
    $this->eventDispatcher->dispatch($event, BaseEvents::CUSTOM);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'event_id' => '',
      'tokens' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['event_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event ID'),
      '#default_value' => $this->configuration['event_id'],
      '#weight' => -20,
      '#description' => $this->t('The ID of the event to be triggered.'),
    ];
    $form['tokens'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tokens to forward'),
      '#default_value' => $this->configuration['tokens'],
      '#description' => $this->t('Comma separated list of token names from the current context, that will be forwarded to the triggered event. These tokens are then also available for subsequent conditions and actions within the current process.'),
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['event_id'] = $form_state->getValue('event_id');
    $this->configuration['tokens'] = $form_state->getValue('tokens');
    parent::submitConfigurationForm($form, $form_state);
  }

}
