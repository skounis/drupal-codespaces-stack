<?php

namespace Drupal\eca_test_array\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_test_array\Event\ArrayEvents;
use Drupal\eca_test_array\Event\ArrayWriteEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA array events.
 *
 * @EcaEvent(
 *   id = "eca_test_array",
 *   deriver = "Drupal\eca_test_array\Plugin\ECA\Event\ArrayEventDeriver"
 * )
 */
class ArrayEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $actions = [];
    $actions['write'] = [
      'label' => 'Static array: write',
      'event_name' => ArrayEvents::WRITE,
      'event_class' => ArrayWriteEvent::class,
      'tags' => Tag::RUNTIME,
    ];
    return $actions;
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();
    return (trim($configuration['key']) === '' ? '*' : $configuration['key']) . '::' . (trim($configuration['value']) === '' ? '*' : $configuration['value']);
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    /** @var \Drupal\eca_test_array\Event\ArrayWriteEvent $event */
    [$key, $value] = explode('::', $wildcard, 2);
    return ($event->key === '*' || $event->key === $key) && ($event->value === '*' || $event->value === $value);
  }

}
