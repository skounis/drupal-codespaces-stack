<?php

namespace Drupal\eca_render\Plugin\ECA\Event;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca\Plugin\PluginUsageInterface;
use Drupal\eca_render\Event\EcaRenderBlockEvent;
use Drupal\eca_render\Event\EcaRenderContextualLinksEvent;
use Drupal\eca_render\Event\EcaRenderEntityEvent;
use Drupal\eca_render\Event\EcaRenderEntityOperationsEvent;
use Drupal\eca_render\Event\EcaRenderExtraFieldEvent;
use Drupal\eca_render\Event\EcaRenderLazyEvent;
use Drupal\eca_render\Event\EcaRenderLocalTasksEvent;
use Drupal\eca_render\Event\EcaRenderViewsFieldEvent;
use Drupal\eca_render\RenderEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of ECA render events.
 *
 * @EcaEvent(
 *   id = "eca_render",
 *   deriver = "Drupal\eca_render\Plugin\ECA\Event\RenderEventDeriver",
 *   eca_version_introduced = "1.1.0"
 * )
 */
class RenderEvent extends EventBase implements PluginUsageInterface {

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * A list of cache backends for invalidation.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface[]
   */
  protected array $cacheBackends = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->cacheBackends[] = $container->get('cache.render');
    if ($instance->moduleHandler->moduleExists('page_cache')) {
      $instance->cacheBackends[] = $container->get('cache.page');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $definitions = [];
    $definitions['block'] = [
      'label' => 'ECA Block',
      'event_name' => RenderEvents::BLOCK,
      'event_class' => EcaRenderBlockEvent::class,
      'tags' => Tag::RUNTIME,
    ];
    $definitions['contextual_links'] = [
      'label' => 'ECA contextual links',
      'event_name' => RenderEvents::CONTEXTUAL_LINKS,
      'event_class' => EcaRenderContextualLinksEvent::class,
      'tags' => Tag::RUNTIME | Tag::CONFIG | Tag::CONTENT,
    ];
    $definitions['local_tasks'] = [
      'label' => 'ECA local tasks',
      'event_name' => RenderEvents::LOCAL_TASKS,
      'event_class' => EcaRenderLocalTasksEvent::class,
      'tags' => Tag::RUNTIME | Tag::CONFIG | Tag::CONTENT,
      'eca_version_introduced' => '2.1.0',
    ];
    $definitions['entity'] = [
      'label' => 'ECA entity',
      'event_name' => RenderEvents::ENTITY,
      'event_class' => EcaRenderEntityEvent::class,
      'tags' => Tag::RUNTIME | Tag::CONFIG | Tag::CONTENT,
      'eca_version_introduced' => '2.0.0',
    ];
    $definitions['entity_operations'] = [
      'label' => 'ECA entity operation links',
      'event_name' => RenderEvents::ENTITY_OPERATIONS,
      'event_class' => EcaRenderEntityOperationsEvent::class,
      'tags' => Tag::RUNTIME | Tag::CONFIG | Tag::CONTENT,
    ];
    $definitions['extra_field'] = [
      'label' => 'ECA Extra field',
      'event_name' => RenderEvents::EXTRA_FIELD,
      'event_class' => EcaRenderExtraFieldEvent::class,
      'tags' => Tag::RUNTIME | Tag::CONTENT,
    ];
    $definitions['views_field'] = [
      'label' => 'ECA Views field',
      'event_name' => RenderEvents::VIEWS_FIELD,
      'event_class' => EcaRenderViewsFieldEvent::class,
      'tags' => Tag::RUNTIME,
    ];
    $definitions['lazy_element'] = [
      'label' => 'ECA lazy element',
      'event_name' => RenderEvents::LAZY_ELEMENT,
      'event_class' => EcaRenderLazyEvent::class,
      'tags' => Tag::RUNTIME,
    ];
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $values = [];
    if ($this->eventClass() === EcaRenderBlockEvent::class) {
      $values += [
        'block_name' => '',
        'block_machine_name' => '',
      ];
    }
    if ($this->eventClass() === EcaRenderEntityEvent::class || $this->eventClass() === EcaRenderEntityOperationsEvent::class || $this->eventClass() === EcaRenderContextualLinksEvent::class || $this->eventClass() === EcaRenderExtraFieldEvent::class) {
      $values += [
        'entity_type_id' => '',
        'bundle' => '',
      ];
    }
    if ($this->eventClass() === EcaRenderContextualLinksEvent::class) {
      $values += [
        'group' => '',
      ];
    }
    if ($this->eventClass() === EcaRenderViewsFieldEvent::class) {
      $values += [
        'name' => '',
      ];
    }
    if ($this->eventClass() === EcaRenderExtraFieldEvent::class) {
      $values += [
        'extra_field_name' => '',
        'extra_field_label' => '',
        'extra_field_description' => '',
        'display_type' => 'display',
        'weight' => '',
        'visible' => FALSE,
      ];
    }
    if ($this->eventClass() === EcaRenderLazyEvent::class) {
      $values += [
        'name' => '',
        'argument' => '',
      ];
    }
    return $values + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($this->eventClass() === EcaRenderBlockEvent::class) {
      $form['block_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Block name'),
        '#default_value' => $this->configuration['block_name'],
        '#description' => $this->t('This block name will be used for being identified in the list of available blocks.'),
        '#required' => TRUE,
        '#weight' => 10,
      ];
    }
    if ($this->eventClass() === EcaRenderContextualLinksEvent::class) {
      $form['group'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Restrict by link group'),
        '#default_value' => $this->configuration['group'],
        '#description' => $this->t('Example: <em>menu</em>'),
        '#weight' => 0,
      ];
    }
    if ($this->eventClass() === EcaRenderEntityEvent::class || $this->eventClass() === EcaRenderEntityOperationsEvent::class || $this->eventClass() === EcaRenderContextualLinksEvent::class || $this->eventClass() === EcaRenderExtraFieldEvent::class) {
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
    }
    if ($this->eventClass() === EcaRenderViewsFieldEvent::class) {
      $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Field name'),
        '#description' => $this->t('The specified name of the field, as it is configured in the view.'),
        '#default_value' => $this->configuration['name'],
        '#required' => TRUE,
        '#weight' => 10,
      ];
    }
    if ($this->eventClass() === EcaRenderExtraFieldEvent::class) {
      $form['extra_field_name'] = [
        '#type' => 'machine_name',
        '#machine_name' => [
          'exists' => [$this, 'alwaysFalse'],
        ],
        '#title' => $this->t('Machine name of the extra field'),
        '#description' => $this->t('The <em>machine name</em> of the extra field. Must only container lowercase alphanumeric characters and underscores.'),
        '#default_value' => $this->configuration['extra_field_name'],
        '#required' => TRUE,
        '#weight' => -200,
      ];
      $form['extra_field_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label of the extra field'),
        '#description' => $this->t('The human-readable label of the extra field.'),
        '#default_value' => $this->configuration['extra_field_label'],
        '#required' => TRUE,
        '#weight' => -190,
      ];
      $form['extra_field_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $this->configuration['extra_field_description'],
        '#required' => FALSE,
        '#weight' => -180,
      ];
      $form['display_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Display type'),
        '#options' => [
          'display' => $this->t('View display'),
          'form' => $this->t('Form display'),
        ],
        '#default_value' => $this->configuration['display_type'],
        '#required' => TRUE,
        '#weight' => -170,
      ];
      $form['weight'] = [
        '#type' => 'number',
        '#title' => $this->t('Weight'),
        '#description' => $this->t('The default weight order. Must be an integer number.'),
        '#default_value' => $this->configuration['weight'],
        '#required' => FALSE,
        '#weight' => -160,
      ];
      $form['visible'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Default visible'),
        '#description' => $this->t('When enabled, the extra field will be automatically displayed by default.'),
        '#default_value' => $this->configuration['visible'],
        '#weight' => -150,
      ];
    }
    if ($this->eventClass() === EcaRenderLazyEvent::class) {
      $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Element name'),
        '#description' => $this->t('The name of the element, as it was specified in the configured action <em>Render: lazy element</em>. In any successor of this event, you have access to following tokens:<ul><li><strong>[name]</strong>: Contains the name of the element.</li><li><strong>[argument]</strong>: Contains the optional argument for the element.</li></ul>'),
        '#default_value' => $this->configuration['name'],
        '#required' => TRUE,
        '#weight' => 10,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    if (($this->getDerivativeId() === 'contextual_links') && !$this->moduleHandler->moduleExists('contextual')) {
      $form_state->setError($form, $this->t("The <em>Contextual Links</em> module must be installed for being able to react upon contextual links."));
    }
    if (($this->getDerivativeId() === 'views_field') && !$this->moduleHandler->moduleExists('views')) {
      $form_state->setError($form, $this->t("The <em>Views</em> module must be installed for being able to react upon ECA Views fields."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if ($this->eventClass() === EcaRenderBlockEvent::class) {
      $this->configuration['block_name'] = $form_state->getValue('block_name');
      $this->configuration['block_machine_name'] = $form_state->getValue('block_machine_name', strtolower(preg_replace("/[^a-zA-Z0-9]+/", "_", trim($this->configuration['block_name']))));
    }
    if ($this->eventClass() === EcaRenderContextualLinksEvent::class) {
      $this->configuration['group'] = $form_state->getValue('group');
    }
    if ($this->eventClass() === EcaRenderEntityEvent::class || $this->eventClass() === EcaRenderEntityOperationsEvent::class || $this->eventClass() === EcaRenderContextualLinksEvent::class || $this->eventClass() === EcaRenderExtraFieldEvent::class) {
      $this->configuration['entity_type_id'] = $form_state->getValue('entity_type_id');
      $this->configuration['bundle'] = $form_state->getValue('bundle');
    }
    if ($this->eventClass() === EcaRenderViewsFieldEvent::class) {
      $this->configuration['name'] = $form_state->getValue('name');
    }
    if ($this->eventClass() === EcaRenderExtraFieldEvent::class) {
      $this->configuration['extra_field_name'] = $form_state->getValue('extra_field_name');
      $this->configuration['extra_field_label'] = $form_state->getValue('extra_field_label');
      $this->configuration['extra_field_description'] = $form_state->getValue('extra_field_description');
      $this->configuration['display_type'] = $form_state->getValue('display_type');
      $this->configuration['weight'] = $form_state->getValue('weight');
      $this->configuration['visible'] = !empty($form_state->getValue('visible'));
    }
    if ($this->eventClass() === EcaRenderLazyEvent::class) {
      $this->configuration['name'] = $form_state->getValue('name');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $derivative_id = $this->getDerivativeId();
    $configuration = $ecaEvent->getConfiguration();
    switch ($derivative_id) {

      case 'block':
        return $configuration['block_machine_name'] ?? '*';

      case 'entity':
      case 'entity_operations':
      case 'contextual_links':
      case 'extra_field':
        if ($derivative_id === 'contextual_links') {
          $wildcard = trim((string) ($configuration['group'] ?? '*'));
          if ($wildcard === '') {
            $wildcard = '*';
          }
          $wildcard .= ':';
        }
        if ($derivative_id === 'extra_field') {
          $wildcard = trim((string) ($configuration['display_type'] ?? '*'));
          $wildcard .= ':';
          $wildcard .= trim((string) ($configuration['extra_field_name'] ?? '*'));
          $wildcard .= ':';
        }
        $wildcard = $wildcard ?? '';
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
        return $wildcard;

      case 'views_field':
        $configuration = $ecaEvent->getConfiguration();
        return $configuration['name'] ?? '*';

      default:
        return parent::generateWildcard($eca_config_id, $ecaEvent);

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event instanceof EcaRenderBlockEvent) {
      return ($event->getBlock()->getDerivativeId() === $wildcard);
    }
    if ($event instanceof EcaRenderContextualLinksEvent) {
      [$w_group, $w_entity_type_ids, $w_bundles] = explode(':', $wildcard, 3);

      if (($w_group !== '*') && !in_array($event->getGroup(), explode(',', $w_group), TRUE)) {
        return FALSE;
      }

      if ($w_entity_type_ids !== '*') {
        if (!($entity = $event->getEntity())) {
          return FALSE;
        }
        if (!in_array($entity->getEntityTypeId(), explode(',', $w_entity_type_ids), TRUE)) {
          return FALSE;
        }
      }

      if ($w_bundles !== '*') {
        if (!($entity = $event->getEntity())) {
          return FALSE;
        }
        if (!in_array($entity->bundle(), explode(',', $w_bundles), TRUE)) {
          return FALSE;
        }
      }

      return TRUE;
    }
    if ($event instanceof EcaRenderEntityEvent || $event instanceof EcaRenderEntityOperationsEvent) {
      [$w_entity_type_ids, $w_bundles] = explode(':', $wildcard);

      if (($w_entity_type_ids !== '*') && !in_array($event->getEntity()->getEntityTypeId(), explode(',', $w_entity_type_ids), TRUE)) {
        return FALSE;
      }

      if (($w_bundles !== '*') && !in_array($event->getEntity()->bundle(), explode(',', $w_bundles), TRUE)) {
        return FALSE;
      }

      return TRUE;
    }
    if ($event instanceof EcaRenderExtraFieldEvent) {
      [$w_display_type, $w_extra_field_name, $w_entity_type_ids, $w_bundles] = explode(':', $wildcard);

      if ($w_display_type !== $event->getDisplayType()) {
        return FALSE;
      }

      if ($w_extra_field_name !== $event->getExtraFieldName()) {
        return FALSE;
      }

      if (($w_entity_type_ids !== '*') && !in_array($event->getEntity()->getEntityTypeId(), explode(',', $w_entity_type_ids), TRUE)) {
        return FALSE;
      }

      if (($w_bundles !== '*') && !in_array($event->getEntity()->bundle(), explode(',', $w_bundles), TRUE)) {
        return FALSE;
      }

      return TRUE;
    }
    if ($event instanceof EcaRenderLazyEvent) {
      return ($event->name === $wildcard) || ($wildcard === '*');
    }
    if ($event instanceof EcaRenderViewsFieldEvent) {
      return (($wildcard === '*') || (($event->getFieldPlugin()->options['name'] ?? '*') === $wildcard));
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function pluginUsed(Eca $eca, string $id): void {
    if (($this->eventClass() === EcaRenderBlockEvent::class) && (method_exists($this->blockManager, 'clearCachedDefinitions'))) {
      $this->blockManager->clearCachedDefinitions();
    }
    if ($this->eventClass() === EcaRenderExtraFieldEvent::class) {
      $this->entityFieldManager->clearCachedFieldDefinitions();
    }
    foreach ($this->cacheBackends as $cache) {
      $cache->invalidateAll();
    }
  }

  /**
   * Helper callback that always returns FALSE.
   *
   * Some machine name fields cannot have a check whether they are already in
   * use. For these elements, this method can be used.
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function alwaysFalse(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      EcaRenderContextualLinksEvent::class,
      EcaRenderEntityEvent::class,
      EcaRenderLazyEvent::class,
      EcaRenderViewsFieldEvent::class,
    ],
    properties: [
      new Token(name: 'argument', description: 'An optional argument for rendering the element.', classes: [
        EcaRenderLazyEvent::class,
      ]),
      new Token(name: 'display', description: 'The entity display.', classes: [
        EcaRenderEntityEvent::class,
        EcaRenderExtraFieldEvent::class,
      ]),
      new Token(name: 'entity', description: 'The entity.', classes: [
        EcaRenderEntityEvent::class,
        EcaRenderExtraFieldEvent::class,
        EcaRenderViewsFieldEvent::class,
      ]),
      new Token(name: 'extra_field_name', description: 'The name of the extra field.', classes: [
        EcaRenderExtraFieldEvent::class,
      ]),
      new Token(name: 'group', description: 'The context group name.', classes: [
        EcaRenderContextualLinksEvent::class,
      ]),
      new Token(name: 'mode', description: 'The view mode.', classes: [
        EcaRenderEntityEvent::class,
        EcaRenderExtraFieldEvent::class,
      ]),
      new Token(name: 'name', description: 'The name that identifies the lazy element for the event.', classes: [
        EcaRenderLazyEvent::class,
      ]),
      new Token(name: 'options', description: 'The options array.', classes: [
        EcaRenderExtraFieldEvent::class,
      ]),
      new Token(name: 'relationship', description: 'Get the relationship entities of the views row.', classes: [
        EcaRenderViewsFieldEvent::class,
      ]),
      new Token(name: 'route_parameters', description: 'The route parameters.', classes: [
        EcaRenderContextualLinksEvent::class,
      ]),
      new Token(name: 'view_display', description: 'The current display of the view.', classes: [
        EcaRenderViewsFieldEvent::class,
      ]),
      new Token(name: 'view_id', description: 'The view ID.', classes: [
        EcaRenderViewsFieldEvent::class,
      ]),
    ]
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];

    if ($event instanceof EcaRenderContextualLinksEvent) {
      $data += [
        'group' => $event->getGroup(),
        'route_parameters' => $event->getRouteParameters(),
      ];
    }
    elseif ($event instanceof EcaRenderEntityEvent) {
      $data += [
        'entity' => $event->getEntity(),
        'display' => $event->getDisplay(),
        'mode' => $event->getViewMode(),
      ];
    }
    elseif ($event instanceof EcaRenderExtraFieldEvent) {
      $data += [
        'extra_field_name' => $event->getExtraFieldName(),
        'options' => $event->getOptions(),
        'entity' => $event->getEntity(),
        'display' => $event->getDisplay(),
        'mode' => $event->getViewMode(),
      ];
    }
    elseif ($event instanceof EcaRenderLazyEvent) {
      $data += [
        'name' => $event->name,
        'argument' => $event->argument,
      ];
    }
    elseif ($event instanceof EcaRenderViewsFieldEvent) {
      $data += [
        'entity' => $event->getEntity(),
        'relationships' => $event->getRelationshipEntities(),
        'view_id' => $event->getFieldPlugin()->view->id(),
        'view_display' => $event->getFieldPlugin()->view->current_display,
      ];
    }

    $data += parent::buildEventData();
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(name: 'BLOCK_CONTEXT', description: 'The value of the block context under the given name of the token.', classes: [EcaRenderBlockEvent::class])]
  #[Token(name: 'ROUTE_ENTITY', description: 'The entity from the route referenced by the token name as route parameter name.', classes: [EcaRenderContextualLinksEvent::class])]
  #[Token(name: 'argument', description: 'An optional argument for rendering the element.', classes: [EcaRenderLazyEvent::class])]
  #[Token(name: 'name', description: 'The name that identifies the lazy element for the event.', classes: [EcaRenderLazyEvent::class])]
  #[Token(name: 'entity', description: 'The entity.', classes: [EcaRenderViewsFieldEvent::class])]
  #[Token(name: 'ENTITY_TYPE', description: 'The entity by entity type, or the related entity by entity type.', classes: [EcaRenderViewsFieldEvent::class])]
  #[Token(name: 'RELATED_ENTITY', description: 'The related entity.', classes: [EcaRenderViewsFieldEvent::class])]
  public function getData(string $key): mixed {
    $event = $this->event;

    if ($event instanceof EcaRenderBlockEvent) {
      $context_definitions = $event->getBlock()->getContextDefinitions();
      if (isset($context_definitions[$key])) {
        $context = $event->getBlock()->getContext($key);
        if ($context->hasContextValue()) {
          return $context->getContextValue();
        }
      }
    }
    elseif ($event instanceof EcaRenderContextualLinksEvent) {
      $routeParameters = $event->getRouteParameters();
      if (isset($routeParameters[$key])) {
        $v = $routeParameters[$key];
        if (is_scalar($v) && $this->entityTypeManager->hasDefinition($key) && ($entity = $this->entityTypeManager->getStorage($key)->load($v))) {
          $v = $entity;
        }
        return $v;
      }
      if ($key === 'entity') {
        $definitions = $this->entityTypeManager->getDefinitions();
        foreach ($routeParameters as $name => $v) {
          if (isset($definitions[$name])) {
            if (is_scalar($v) && ($entity = $this->entityTypeManager->getStorage($name)->load($v))) {
              $v = $entity;
            }
            return $v;
          }
        }
      }
    }
    elseif ($event instanceof EcaRenderLazyEvent) {
      if ($key === 'argument') {
        return $event->argument;
      }
      if ($key === 'name') {
        return $event->name;
      }
    }
    elseif ($event instanceof EcaRenderViewsFieldEvent) {
      if ($key === 'entity' || $key === $event->getEntity()->getEntityTypeId()) {
        return $event->getEntity();
      }
      foreach ($event->getRelationshipEntities() as $i => $entity) {
        if ($key === $i || $key === $entity->getEntityTypeId()) {
          return $entity;
        }
      }
    }

    return parent::getData($key);
  }

}
