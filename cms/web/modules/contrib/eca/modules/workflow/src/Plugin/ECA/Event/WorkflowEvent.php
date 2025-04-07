<?php

namespace Drupal\eca_workflow\Plugin\ECA\Event;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca\Service\ContentEntityTypes;
use Drupal\eca_workflow\Event\TransitionEventContent;
use Drupal\eca_workflow\WorkflowEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA workflow events.
 *
 * @EcaEvent(
 *   id = "workflow",
 *   deriver = "Drupal\eca_workflow\Plugin\ECA\Event\WorkflowEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class WorkflowEvent extends EventBase {

  /**
   * The content entity types service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setEntityTypes($container->get('eca.service.content_entity_types'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $definitions = [];
    $definitions['transition'] = [
      'label' => 'Workflow: state transition',
      'event_name' => WorkflowEvents::TRANSITION,
      'event_class' => TransitionEventContent::class,
      'tags' => Tag::CONTENT | Tag::PERSISTENT | Tag::BEFORE,
    ];
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if ($this->eventClass() === TransitionEventContent::class) {
      $values = [
        'type' => ContentEntityTypes::ALL,
        'from_state' => '',
        'to_state' => '',
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
    if ($this->eventClass() === TransitionEventContent::class) {
      $form['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type (and bundle)'),
        '#options' => $this->entityTypes->getTypesAndBundles(TRUE),
        '#default_value' => $this->configuration['type'],
        '#weight' => 10,
      ];
      $form['from_state'] = [
        '#type' => 'textfield',
        '#title' => $this->t('From state'),
        '#description' => $this->t('Optionally restrict to the machine name of the previous state.'),
        '#default_value' => $this->configuration['from_state'],
        '#weight' => 20,
      ];
      $form['to_state'] = [
        '#type' => 'textfield',
        '#title' => $this->t('To state'),
        '#description' => $this->t('Optionally restrict to the machine name of the new state.'),
        '#default_value' => $this->configuration['to_state'],
        '#weight' => 30,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if ($this->eventClass() === TransitionEventContent::class) {
      $this->configuration['type'] = $form_state->getValue('type');
      $this->configuration['from_state'] = trim($form_state->getValue('from_state', ''));
      $this->configuration['to_state'] = trim($form_state->getValue('to_state', ''));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    switch ($this->getDerivativeId()) {

      case 'transition':
        $config = $ecaEvent->getConfiguration();
        $type = $config['type'] ?? ContentEntityTypes::ALL;
        if ($type === ContentEntityTypes::ALL) {
          $wildcard = '*::*';
        }
        else {
          [$entityType, $bundle] = array_merge(explode(' ', $type), [ContentEntityTypes::ALL]);
          if ($bundle === ContentEntityTypes::ALL) {
            $wildcard = $entityType . '::*';
          }
          else {
            $wildcard = $entityType . '::' . $bundle;
          }
        }
        $wildcard .= in_array(($config['from_state'] ?? ''), ['', '*']) ? '::*' : '::' . $config['from_state'];
        $wildcard .= in_array(($config['to_state'] ?? ''), ['', '*']) ? '::*' : '::' . $config['to_state'];
        return $wildcard;

      default:
        return parent::generateWildcard($eca_config_id, $ecaEvent);

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    /** @var \Drupal\eca_workflow\Event\TransitionEventContent $event */
    [$w_entity_type_id, $w_entity_bundle, $w_from_state, $w_to_state] = explode('::', $wildcard);
    $entity = $event->getEntity();
    if (($w_entity_type_id !== '*') && ($w_entity_type_id !== $entity->getEntityTypeId())) {
      return FALSE;
    }
    if (($w_entity_bundle !== '*') && ($w_entity_bundle !== $entity->bundle())) {
      return FALSE;
    }
    if (($w_from_state !== '*') && ($w_from_state !== $event->getFromState())) {
      return FALSE;
    }
    if (($w_to_state !== '*') && ($w_to_state !== $event->getToState())) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Set the content entity types service.
   *
   * @param \Drupal\eca\Service\ContentEntityTypes $entity_types
   *   The content entity types service.
   */
  public function setEntityTypes(ContentEntityTypes $entity_types): void {
    $this->entityTypes = $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(name: 'from_state', description: 'The source workflow state of the entity.', classes: [TransitionEventContent::class])]
  #[Token(name: 'to_state', description: 'The destination workflow state of the entity.', classes: [TransitionEventContent::class])]
  public function getData(string $key): mixed {
    $event = $this->event;
    if ($event instanceof TransitionEventContent) {
      if ($key === 'from_state' && $fromState = $event->getFromState()) {
        return $fromState;
      }
      if ($key === 'to_state') {
        return $event->getToState();
      }
    }
    return parent::getData($key);
  }

}
