<?php

namespace Drupal\eca_access\Plugin\ECA\Event;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_access\AccessEvents;
use Drupal\eca_access\Event\CreateAccess;
use Drupal\eca_access\Event\EntityAccess;
use Drupal\eca_access\Event\FieldAccess;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of ECA access events.
 *
 * @EcaEvent(
 *   id = "access",
 *   deriver = "Drupal\eca_access\Plugin\ECA\Event\AccessEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class AccessEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $definitions = [];
    $definitions['entity'] = [
      'label' => 'Determining entity access',
      'event_name' => AccessEvents::ENTITY,
      'event_class' => EntityAccess::class,
      'tags' => Tag::RUNTIME | Tag::EPHEMERAL,
    ];
    $definitions['field'] = [
      'label' => 'Determining entity field access',
      'event_name' => AccessEvents::FIELD,
      'event_class' => FieldAccess::class,
      'tags' => Tag::RUNTIME | Tag::EPHEMERAL,
    ];
    $definitions['create'] = [
      'label' => 'Determining entity create access',
      'event_name' => AccessEvents::CREATE,
      'event_class' => CreateAccess::class,
      'tags' => Tag::RUNTIME | Tag::EPHEMERAL,
      'eca_version_introduced' => '1.1.0',
    ];
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $is_field_event = $this->eventClass() === FieldAccess::class;
    $values = ($is_field_event ? ['field_name' => ''] : []) +
      [
        'entity_type_id' => '',
        'bundle' => '',
        'operation' => '',
      ] + parent::defaultConfiguration();
    if ($this->eventClass() === CreateAccess::class) {
      unset($values['operation']);
      $values['langcode'] = '';
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $is_field_event = $this->eventClass() === FieldAccess::class;
    $is_create_event = $this->eventClass() === CreateAccess::class;
    $form['account_token_info'] = [
      '#type' => 'container',
      '#markup' => $this->t('For any successor of this event, the account that asks for access is available under the <strong>[account]</strong> token. Example: <strong>[account:uid]</strong> provides the user ID of the account.'),
      '#weight' => 0,
    ];
    if ($is_field_event) {
      $form['event_token_info'] = [
        '#type' => 'container',
        '#markup' => $this->t('Furthermore, following data of the event is available:<ul><li><strong>[event:operation]</strong> holds the requested operation, such as "view".</li><li><strong>[event:field]</strong> holds the machine name of the field.</li></ul>'),
        '#weight' => 1,
      ];
    }
    elseif (!$is_field_event) {
      $form['event_token_info'] = [
        '#type' => 'container',
        '#markup' => $this->t('Furthermore, following data of the event is available:<ul><li><strong>[event:operation]</strong> holds the requested operation, such as "view".</li></ul>'),
        '#weight' => 1,
      ];
    }
    $form['entity_type_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict by entity type ID'),
      '#default_value' => $this->configuration['entity_type_id'],
      '#description' => $this->t('Example: <em>node, taxonomy_term, user</em>'),
      '#weight' => 10,
    ];
    $form['bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict by entity bundle'),
      '#default_value' => $this->configuration['bundle'],
      '#description' => $this->t('Example: <em>article, tags</em>'),
      '#weight' => 20,
    ];
    if (!$is_create_event) {
      $form['operation'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Restrict by operation'),
        '#default_value' => $this->configuration['operation'],
        '#description' => $is_field_event ? $this->t('Example: <em>view, edit</em>') : $this->t('Example: <em>view, update, delete</em>'),
        '#weight' => 30,
      ];
    }
    if ($is_field_event) {
      $form['field_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Restrict by field name'),
        '#default_value' => $this->configuration['field_name'],
        '#description' => $this->t('Example: <em>title, body, field_myfield</em>'),
        '#weight' => 40,
      ];
    }
    if ($is_create_event) {
      $form['langcode'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Restrict by language code'),
        '#default_value' => $this->configuration['langcode'],
        '#description' => $this->t('Example: <em>en</em>'),
        '#weight' => 40,
      ];
    }

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $is_field_event = $this->eventClass() === FieldAccess::class;
    $is_create_event = $this->eventClass() === CreateAccess::class;
    $this->configuration['entity_type_id'] = $form_state->getValue('entity_type_id');
    $this->configuration['bundle'] = $form_state->getValue('bundle');
    if (!$is_create_event) {
      $this->configuration['operation'] = $form_state->getValue('operation');
    }
    if ($is_field_event) {
      $this->configuration['field_name'] = $form_state->getValue('field_name');
    }
    if ($is_create_event) {
      $this->configuration['langcode'] = $form_state->getValue('langcode');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();
    $is_create_event = $this->eventClass() === CreateAccess::class;

    $wildcard = '';
    $entity_type_ids = [];
    if (!empty($configuration['entity_type_id'])) {
      foreach (explode(',', $configuration['entity_type_id']) as $entity_type_id) {
        $entity_type_id = strtolower(trim($entity_type_id));
        if ($entity_type_id !== '') {
          $entity_type_ids[] = $entity_type_id;
        }
      }
    }
    if ($entity_type_ids) {
      $wildcard .= implode(',', $entity_type_ids);
    }
    else {
      $wildcard .= '*';
    }

    $wildcard .= ':';
    $bundles = [];
    if (!empty($configuration['bundle'])) {
      foreach (explode(',', $configuration['bundle']) as $bundle) {
        $bundle = strtolower(trim($bundle));
        if ($bundle !== '') {
          $bundles[] = $bundle;
        }
      }
    }
    if ($bundles) {
      $wildcard .= implode(',', $bundles);
    }
    else {
      $wildcard .= '*';
    }

    if (!$is_create_event) {
      $wildcard .= ':';
      $operations = [];
      if (!empty($configuration['operation'])) {
        foreach (explode(',', $configuration['operation']) as $operation) {
          $operation = trim($operation);
          if ($operation !== '') {
            $operations[] = $operation;
          }
        }
      }
      if ($operations) {
        $wildcard .= implode(',', $operations);
      }
      else {
        $wildcard .= '*';
      }
    }

    $is_field_event = $this->eventClass() === FieldAccess::class;
    if ($is_field_event) {
      $wildcard .= ':';
      $field_names = [];
      if (!empty($configuration['field_name'])) {
        foreach (explode(',', $configuration['field_name']) as $field_name) {
          $field_name = trim($field_name);
          if ($field_name !== '') {
            $field_names[] = $field_name;
          }
        }
      }
      if ($field_names) {
        $wildcard .= implode(',', $field_names);
      }
      else {
        $wildcard .= '*';
      }
    }

    if ($is_create_event) {
      $wildcard .= ':';
      $langcodes = [];
      if (!empty($configuration['langcode'])) {
        foreach (explode(',', $configuration['langcode']) as $langcode) {
          $langcode = trim($langcode);
          if ($langcode !== '') {
            $langcodes[] = $langcode;
          }
        }
      }
      if ($langcodes) {
        $wildcard .= implode(',', $langcodes);
      }
      else {
        $wildcard .= '*';
      }
    }

    return $wildcard;
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    $parts = explode(':', $wildcard);

    switch ($event_name) {

      case AccessEvents::ENTITY:
      case AccessEvents::FIELD:
        [$w_entity_type_ids, $w_bundles, $w_operations] = $parts;
        /** @var \Drupal\eca_access\Event\EntityAccess $event */
        if (($w_entity_type_ids !== '*') && !in_array($event->getEntity()->getEntityTypeId(), explode(',', $w_entity_type_ids), TRUE)) {
          return FALSE;
        }
        if (($w_bundles !== '*') && !in_array($event->getEntity()->bundle(), explode(',', $w_bundles), TRUE)) {
          return FALSE;
        }
        if (($w_operations !== '*') && !in_array($event->getOperation(), explode(',', $w_operations), TRUE)) {
          return FALSE;
        }
        if ($event_name === AccessEvents::FIELD) {
          $w_field_names = end($parts);
          /** @var \Drupal\eca_access\Event\FieldAccess $event */
          if (($w_field_names !== '*') && !in_array($event->getFieldName(), explode(',', $w_field_names), TRUE)) {
            return FALSE;
          }
        }
        break;

      case AccessEvents::CREATE:
        [$w_entity_type_ids, $w_bundles, $w_langcodes] = $parts;
        /** @var \Drupal\eca_access\Event\CreateAccess $event */
        $event_context = $event->getContext();
        if (($w_entity_type_ids !== '*') && !in_array($event_context['entity_type_id'], explode(',', $w_entity_type_ids), TRUE)) {
          return FALSE;
        }
        if (($w_bundles !== '*') && !in_array($event->getEntityBundle(), explode(',', $w_bundles), TRUE)) {
          return FALSE;
        }
        if (($w_langcodes !== '*') && !in_array($event_context['langcode'], explode(',', $w_langcodes), TRUE)) {
          return FALSE;
        }
        break;

      default:
        throw new \InvalidArgumentException(sprintf("Given event %s is not supported.", $event_name));

    }

    // Initialize with a neutral result.
    /** @var \Drupal\eca\Event\AccessEventInterface $event */
    $event->setAccessResult(AccessResult::neutral());

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    properties: [
      new Token(name: 'context', description: 'Contains a list of properties depending on the context of the event.', classes: [
        CreateAccess::class,
      ]),
      new Token(name: 'entity_bundle', description: 'The bundle of the entity.', classes: [
        CreateAccess::class,
        EntityAccess::class,
      ]),
      new Token(name: 'entity_id', description: 'The entity ID, only available if the entity is not new.', classes: [
        EntityAccess::class,
      ]),
      new Token(name: 'entity_type', description: 'The entity type.', classes: [
        EntityAccess::class,
      ]),
      new Token(name: 'field', description: 'The name of the field.', classes: [
        FieldAccess::class,
      ]),
      new Token(name: 'operation', description: 'The operation with which the entity should be accessed, e.g. "view", "update", etc.', classes: [
        EntityAccess::class,
      ]),
      new Token(name: 'uid', description: 'The ID of the user account of the event.', classes: [
        AccessEventInterface::class,
      ]),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];

    if ($event instanceof AccessEventInterface) {
      $data += [
        'uid' => $event->getAccount()->id(),
      ];
    }
    if ($event instanceof EntityAccess) {
      $entity = $event->getEntity();
      $data += [
        'operation' => $event->getOperation(),
        'entity_type' => $entity->getEntityTypeId(),
        'entity_bundle' => $entity->bundle(),
      ];
      if (!$entity->isNew()) {
        $data['entity_id'] = $entity->id();
      }
    }
    if ($event instanceof FieldAccess) {
      $data += [
        'field' => $event->getFieldName(),
      ];
    }
    if ($event instanceof CreateAccess) {
      $data += [
        'context' => [],
        'entity_bundle' => $event->getEntityBundle(),
      ];
      $context = $event->getContext();
      foreach ($context as $k => $v) {
        if (is_scalar($v)) {
          $data['context'][$k] = $v;
        }
      }
      if (isset($context['entity_type_id'])) {
        $data['entity_type'] = $context['entity_type_id'];
      }
    }

    $data += parent::buildEventData();
    return $data;
  }

}
