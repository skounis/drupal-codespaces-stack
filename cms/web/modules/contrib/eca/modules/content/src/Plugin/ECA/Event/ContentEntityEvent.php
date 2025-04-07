<?php

namespace Drupal\eca_content\Plugin\ECA\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\CleanupInterface;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca\Service\ContentEntityTypes;
use Drupal\eca_content\Event\ContentEntityBaseBundle;
use Drupal\eca_content\Event\ContentEntityBaseContentEntity;
use Drupal\eca_content\Event\ContentEntityBundleCreate;
use Drupal\eca_content\Event\ContentEntityBundleDelete;
use Drupal\eca_content\Event\ContentEntityCreate;
use Drupal\eca_content\Event\ContentEntityCustomEvent;
use Drupal\eca_content\Event\ContentEntityDelete;
use Drupal\eca_content\Event\ContentEntityEvents;
use Drupal\eca_content\Event\ContentEntityFieldValuesInit;
use Drupal\eca_content\Event\ContentEntityInsert;
use Drupal\eca_content\Event\ContentEntityLoad;
use Drupal\eca_content\Event\ContentEntityPreDelete;
use Drupal\eca_content\Event\ContentEntityPreLoad;
use Drupal\eca_content\Event\ContentEntityPreSave;
use Drupal\eca_content\Event\ContentEntityPrepareForm;
use Drupal\eca_content\Event\ContentEntityPrepareView;
use Drupal\eca_content\Event\ContentEntityRevisionCreate;
use Drupal\eca_content\Event\ContentEntityRevisionDelete;
use Drupal\eca_content\Event\ContentEntityStorageLoad;
use Drupal\eca_content\Event\ContentEntityTranslationCreate;
use Drupal\eca_content\Event\ContentEntityTranslationDelete;
use Drupal\eca_content\Event\ContentEntityTranslationInsert;
use Drupal\eca_content\Event\ContentEntityUpdate;
use Drupal\eca_content\Event\ContentEntityValidate;
use Drupal\eca_content\Event\ContentEntityView;
use Drupal\eca_content\Event\ContentEntityViewModeAlter;
use Drupal\eca_content\Event\FieldSelectionBase;
use Drupal\eca_content\Event\OptionsSelection;
use Drupal\eca_content\Event\ReferenceSelection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA Events for content entities.
 *
 * @EcaEvent(
 *   id = "content_entity",
 *   deriver = "Drupal\eca_content\Plugin\ECA\Event\ContentEntityEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class ContentEntityEvent extends EventBase implements CleanupInterface {

  /**
   * A stack of selection event instances.
   *
   * An instance will be removed by
   * \Drupal\eca_content\Plugin\ECA\Event\ContentEntityEvent::cleanupAfterSuccessors.
   *
   * @var \Drupal\eca_content\Event\OptionsSelection[]
   */
  protected static array $optionsSelections = [];

  /**
   * A stack of selection event instances.
   *
   * An instance will be removed by
   * \Drupal\eca_content\Plugin\ECA\Event\ContentEntityEvent::cleanupAfterSuccessors.
   *
   * @var \Drupal\eca_content\Event\ReferenceSelection[]
   */
  protected static array $referenceSelections = [];

  /**
   * The entity type service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypes = $container->get('eca.service.content_entity_types');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'bundlecreate' => [
        'label' => 'Initialize content entity bundle',
        'description' => 'An entity bundle object is being created (instantiated) on runtime, without being saved.',
        'event_name' => ContentEntityEvents::BUNDLECREATE,
        'event_class' => ContentEntityBundleCreate::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'bundledelete' => [
        'label' => 'Delete content entity bundle',
        'event_name' => ContentEntityEvents::BUNDLEDELETE,
        'event_class' => ContentEntityBundleDelete::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'create' => [
        'label' => 'Initialize content entity',
        'description' => 'An entity object is being created (instantiated) on runtime, without being saved.',
        'event_name' => ContentEntityEvents::CREATE,
        'event_class' => ContentEntityCreate::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
      ],
      'revisioncreate' => [
        'label' => 'Initialize content entity revision',
        'description' => 'An entity revision object is being created (instantiated) on runtime, without being saved.',
        'event_name' => ContentEntityEvents::REVISIONCREATE,
        'event_class' => ContentEntityRevisionCreate::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
      ],
      'preload' => [
        'label' => 'Preload content entity',
        'event_name' => ContentEntityEvents::PRELOAD,
        'event_class' => ContentEntityPreLoad::class,
        'tags' => Tag::READ | Tag::BEFORE,
      ],
      'load' => [
        'label' => 'Load content entity',
        'event_name' => ContentEntityEvents::LOAD,
        'event_class' => ContentEntityLoad::class,
        'tags' => Tag::CONTENT | Tag::READ | Tag::AFTER,
      ],
      'storageload' => [
        'label' => 'Load content entity from storage',
        'event_name' => ContentEntityEvents::STORAGELOAD,
        'event_class' => ContentEntityStorageLoad::class,
        'tags' => Tag::CONTENT | Tag::READ | Tag::PERSISTENT | Tag::AFTER,
      ],
      'presave' => [
        'label' => 'Presave content entity',
        'description' => 'Before a new or existing entity gets saved (persistently created or changed).',
        'event_name' => ContentEntityEvents::PRESAVE,
        'event_class' => ContentEntityPreSave::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::BEFORE,
      ],
      'insert' => [
        'label' => 'Insert content entity',
        'description' => 'After a new entity got saved (persistently created).',
        'event_name' => ContentEntityEvents::INSERT,
        'event_class' => ContentEntityInsert::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'update' => [
        'label' => 'Update content entity',
        'description' => 'After an existing entity got saved (persistently changed).',
        'event_name' => ContentEntityEvents::UPDATE,
        'event_class' => ContentEntityUpdate::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'translationcreate' => [
        'label' => 'Initialize content entity translation',
        'description' => 'An entity translation object is being created (instantiated) on runtime, without being saved.',
        'event_name' => ContentEntityEvents::TRANSLATIONCREATE,
        'event_class' => ContentEntityTranslationCreate::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
      ],
      'translationinsert' => [
        'label' => 'Insert content entity translation',
        'description' => 'After a new entity translation got saved (persistently created).',
        'event_name' => ContentEntityEvents::TRANSLATIONINSERT,
        'event_class' => ContentEntityTranslationInsert::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'translationdelete' => [
        'label' => 'Delete content entity translation',
        'event_name' => ContentEntityEvents::TRANSLATIONDELETE,
        'event_class' => ContentEntityTranslationDelete::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'predelete' => [
        'label' => 'Predelete content entity',
        'event_name' => ContentEntityEvents::PREDELETE,
        'event_class' => ContentEntityPreDelete::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::BEFORE,
      ],
      'delete' => [
        'label' => 'Delete content entity',
        'event_name' => ContentEntityEvents::DELETE,
        'event_class' => ContentEntityDelete::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'revisiondelete' => [
        'label' => 'Delete content entity revision',
        'event_name' => ContentEntityEvents::REVISIONDELETE,
        'event_class' => ContentEntityRevisionDelete::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'view' => [
        'label' => 'View content entity',
        'event_name' => ContentEntityEvents::VIEW,
        'event_class' => ContentEntityView::class,
        'tags' => Tag::CONTENT | Tag::RUNTIME | Tag::VIEW | Tag::BEFORE,
      ],
      'viewmodealter' => [
        'label' => 'Alter entity view mode',
        'event_name' => ContentEntityEvents::VIEWMODEALTER,
        'event_class' => ContentEntityViewModeAlter::class,
        'tags' => Tag::CONTENT | Tag::RUNTIME | Tag::VIEW | Tag::BEFORE,
        'eca_version_introduced' => '2.0.0',
      ],
      'prepareview' => [
        'label' => 'Prepare content entity view',
        'event_name' => ContentEntityEvents::PREPAREVIEW,
        'event_class' => ContentEntityPrepareView::class,
        'tags' => Tag::CONTENT | Tag::RUNTIME | Tag::VIEW | Tag::BEFORE,
      ],
      'prepareform' => [
        'label' => 'Prepare content entity form',
        'event_name' => ContentEntityEvents::PREPAREFORM,
        'event_class' => ContentEntityPrepareForm::class,
        'tags' => Tag::CONTENT | Tag::RUNTIME | Tag::VIEW | Tag::BEFORE,
      ],
      'validate' => [
        'label' => 'Validate content entity',
        'description' => 'When an entity is undergoing validation.',
        'event_name' => ContentEntityEvents::VALIDATE,
        'event_class' => ContentEntityValidate::class,
        'tags' => Tag::RUNTIME | Tag::CONTENT,
        'eca_version_introduced' => '2.1.0',
      ],
      'fieldvaluesinit' => [
        'label' => 'Init content entity field values',
        'event_name' => ContentEntityEvents::FIELDVALUESINIT,
        'event_class' => ContentEntityFieldValuesInit::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
      ],
      'custom' => [
        'label' => 'ECA custom event (entity-aware)',
        'event_name' => ContentEntityEvents::CUSTOM,
        'event_class' => ContentEntityCustomEvent::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
      ],
      'reference_selection' => [
        'label' => 'Entity reference field selection',
        'event_name' => ContentEntityEvents::REFERENCE_SELECTION,
        'event_class' => ReferenceSelection::class,
        'tags' => Tag::RUNTIME | Tag::CONTENT,
      ],
      'options_selection' => [
        'label' => 'Options field selection',
        'event_name' => ContentEntityEvents::OPTIONS_SELECTION,
        'event_class' => OptionsSelection::class,
        'tags' => Tag::RUNTIME | Tag::CONTENT,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if ($this->eventClass() === ContentEntityCustomEvent::class) {
      $values = [
        'event_id' => '',
      ];
    }
    else {
      $values = [
        'type' => ContentEntityTypes::ALL,
      ];
      if (is_subclass_of($this->eventClass(), FieldSelectionBase::class)) {
        $values['field_name'] = '';
        $values['token_name'] = '';
      }
    }
    return $values + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($this->eventClass() === ContentEntityCustomEvent::class) {
      $form['event_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Event ID'),
        '#default_value' => $this->configuration['event_id'],
        '#description' => $this->t('The id of the custom event (entity aware). Leave empty to trigger all entity aware events.'),
      ];
    }
    else {
      $form['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type (and bundle)'),
        '#options' => $this->entityTypes->getTypesAndBundles(TRUE),
        '#default_value' => $this->configuration['type'],
      ];
      if (is_subclass_of($this->eventClass(), FieldSelectionBase::class)) {
        $form['field_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Restrict by field (machine name)'),
          '#default_value' => $this->configuration['field_name'],
          '#description' => $this->t('The machine name of the field to restrict the event trigger.'),
        ];
        $form['token_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Token name holding the selection'),
          '#default_value' => $this->configuration['token_name'],
          '#description' => $this->t('The name of the token to hold the selection.'),
          '#eca_token_reference' => TRUE,
        ];
      }
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if ($this->eventClass() === ContentEntityCustomEvent::class) {
      $this->configuration['event_id'] = $form_state->getValue('event_id');
    }
    else {
      $this->configuration['type'] = $form_state->getValue('type');
      if (is_subclass_of($this->eventClass(), FieldSelectionBase::class)) {
        $this->configuration['field_name'] = $form_state->getValue('field_name');
        $this->configuration['token_name'] = $form_state->getValue('token_name');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    /** @var \Drupal\eca\Plugin\ECA\Event\EventBase $plugin */
    $plugin = $ecaEvent->getPlugin();
    switch ($plugin->getDerivativeId()) {

      case 'custom':
        $configuration = $ecaEvent->getConfiguration();
        return isset($configuration['event_id']) ? trim($configuration['event_id']) : '';

      case 'preload':
        $type = $ecaEvent->getConfiguration()['type'] ?? ContentEntityTypes::ALL;
        if ($type === ContentEntityTypes::ALL) {
          return '*';
        }
        [$entityType] = explode(' ', $type);
        return $entityType;

      case 'reference_selection':
      case 'options_selection':
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
        if (empty($config['field_name'])) {
          $wildcard .= '::*';
        }
        else {
          $wildcard .= '::' . $config['field_name'];
        }
        return $wildcard;

      default:
        $type = $ecaEvent->getConfiguration()['type'] ?? ContentEntityTypes::ALL;
        if ($type === ContentEntityTypes::ALL) {
          return '*';
        }
        [$entityType, $bundle] = array_merge(explode(' ', $type), [ContentEntityTypes::ALL]);
        if ($bundle === ContentEntityTypes::ALL) {
          return $entityType;
        }
        return $entityType . '::' . $bundle;

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event instanceof ContentEntityBaseBundle) {
      return in_array($wildcard, [
        '*',
        $event->getEntityTypeId(),
        $event->getEntityTypeId() . '::' . $event->getBundle(),
      ], TRUE);
    }
    if ($event instanceof ContentEntityCustomEvent) {
      return ($event->getEventId() === $wildcard) || ($wildcard === '');
    }
    if ($event instanceof ContentEntityBaseContentEntity) {
      $entity = $event->getEntity();
      return in_array($wildcard, [
        '*',
        $entity->getEntityTypeId(),
        $entity->getEntityTypeId() . '::' . $entity->bundle(),
      ], TRUE);
    }
    if ($event instanceof ContentEntityPreLoad) {
      return in_array($wildcard, ['*', $event->getEntityTypeId()], TRUE);
    }
    if ($event instanceof OptionsSelection) {
      if (!$event->hasEntity()) {
        // Can't do anything without an entity and without a specified field.
        return FALSE;
      }

      $entity = $event->getEntity();
      $field_name = $event->fieldStorageDefinition->getName();
      $candidates = ['*::*::*'];
      $candidates[] = '*::*::' . trim($field_name);
      $candidates[] = $entity->getEntityTypeId() . '::*::*';
      $candidates[] = $entity->getEntityTypeId() . '::' . $entity->bundle() . '::*';
      $candidates[] = $entity->getEntityTypeId() . '::*::' . trim($field_name);
      $candidates[] = $entity->getEntityTypeId() . '::' . $entity->bundle() . '::' . trim($field_name);

      if (in_array($wildcard, $candidates, TRUE)) {
        self::$optionsSelections[] = $event;
        return TRUE;
      }
      return FALSE;
    }
    if ($event instanceof ReferenceSelection) {
      $config = $event->selection->getConfiguration();
      /** @var \Drupal\Core\Entity\ContentEntityInterface|null $entity */
      $entity = $config['entity'] ?? NULL;
      $field_name = $config['field_name'] ?? NULL;
      if (!$entity || !$field_name) {
        // Can't do anything without an entity and without a specified field.
        return FALSE;
      }

      $candidates = ['*::*::*'];
      $candidates[] = '*::*::' . trim($field_name);
      $candidates[] = $entity->getEntityTypeId() . '::*::*';
      $candidates[] = $entity->getEntityTypeId() . '::' . $entity->bundle() . '::*';
      $candidates[] = $entity->getEntityTypeId() . '::*::' . trim($field_name);
      $candidates[] = $entity->getEntityTypeId() . '::' . $entity->bundle() . '::' . trim($field_name);

      if (in_array($wildcard, $candidates, TRUE)) {
        self::$referenceSelections[] = $event;
        return TRUE;
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupAfterSuccessors(): void {
    switch ($this->getDerivativeId()) {

      case 'reference_selection':
        if (!($event = array_pop(self::$referenceSelections))) {
          return;
        }
        if (!($token_name = $this->configuration['token_name'] ?? NULL)) {
          return;
        }

        $entities = $this->tokenService->hasTokenData($token_name) ?
          $this->tokenService->getTokenData($token_name) : [];
        if ($entities instanceof EntityInterface) {
          $entities = [$entities];
        }
        elseif (!is_iterable($entities)) {
          $entities = [];
        }

        $event->selection->referenceableEntities = [];
        $config = $event->selection->getConfiguration();
        $target_type = $config['target_type'];
        foreach ($entities as $entity) {
          while ($entity instanceof TypedDataInterface) {
            $entity = $entity->getValue();
          }
          if (is_scalar($entity)) {
            $entity = $this->entityTypeManager->getStorage($target_type)
              ->load($entity);
          }
          if (!($entity instanceof EntityInterface) || ($target_type !== $entity->getEntityTypeId()) || in_array($entity, $event->selection->referenceableEntities, TRUE)) {
            continue;
          }
          $event->selection->referenceableEntities[] = $entity;
        }
        return;

      case 'options_selection':
        if (!($event = array_pop(self::$optionsSelections))) {
          return;
        }
        if (!($token_name = $this->configuration['token_name'] ?? NULL)) {
          return;
        }

        $values = $this->tokenService->hasTokenData($token_name) ?
          $this->tokenService->getTokenData($token_name) : [];
        if (!is_iterable($values)) {
          $values = [];
        }

        $event->allowedValues = [];
        foreach ($values as $k => $v) {
          if ($v instanceof TypedDataInterface) {
            $v = $v->getString();
          }
          if (is_object($v) && !($v instanceof TranslatableMarkup) && method_exists($v, '__toString')) {
            $v = (string) $v;
          }
          if (is_scalar($v)) {
            $event->allowedValues[$k] = (string) $v;
          }
          elseif ($v instanceof TranslatableMarkup) {
            $event->allowedValues[$k] = $v;
          }
          elseif (is_null($v)) {
            $event->allowedValues[$k] = $k;
          }
        }
        return;

    }
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      ContentEntityPreLoad::class,
    ],
    properties: [
      new Token(name: 'entity_type_id', description: 'The entity type.'),
      new Token(name: 'ids', description: 'The IDs of the entities.'),
    ],
  )]
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      ContentEntityViewModeAlter::class,
    ],
    properties: [
      new Token(name: 'view_mode', description: 'The view mode machine name.'),
    ],
  )]
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      ContentEntityBaseBundle::class,
    ],
    properties: [
      new Token(name: 'entity_type_id', description: 'The entity type.'),
      new Token(name: 'bundle', description: 'The bundle machine name.'),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];

    if ($event instanceof ContentEntityPreLoad) {
      $data += [
        'entity_type_id' => $event->getEntityTypeId(),
        'ids' => $event->getIds(),
      ];
    }
    elseif ($event instanceof ContentEntityViewModeAlter) {
      $data += [
        'view_mode' => $event->getViewMode(),
      ];
    }
    elseif ($event instanceof ContentEntityBaseBundle) {
      $data += [
        'entity_type_id' => $event->getEntityTypeId(),
        'bundle' => $event->getBundle(),
      ];
    }

    $data += parent::buildEventData();
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'entity_view_mode',
    description: 'The entity view mode.',
    classes: [
      ContentEntityPrepareView::class,
      ContentEntityView::class,
    ],
  )]
  public function getData(string $key): mixed {
    $event = $this->event;
    if ($key === 'entity_view_mode' && ($event instanceof ContentEntityPrepareView || $event instanceof ContentEntityView || $event instanceof ContentEntityViewModeAlter)) {
      return $event->getViewMode();
    }
    return parent::getData($key);
  }

}
