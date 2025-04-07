<?php

namespace Drupal\eca_form\Plugin\ECA\Event;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca\Token\TokenServices;
use Drupal\eca_form\Event\FormAfterBuild;
use Drupal\eca_form\Event\FormBuild;
use Drupal\eca_form\Event\FormEvents;
use Drupal\eca_form\Event\FormProcess;
use Drupal\eca_form\Event\FormSubmit;
use Drupal\eca_form\Event\FormValidate;
use Drupal\eca_form\Event\InlineEntityFormBuild;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA Events for the form API.
 *
 * @EcaEvent(
 *   id = "form",
 *   deriver = "Drupal\eca_form\Plugin\ECA\Event\FormEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class FormEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $actions = [];
    $actions['form_build'] = [
      'label' => 'Build form',
      'event_name' => FormEvents::BUILD,
      'event_class' => FormBuild::class,
      'tags' => Tag::VIEW | Tag::RUNTIME | Tag::BEFORE,
    ];
    $actions['form_process'] = [
      'label' => 'Process form',
      'event_name' => FormEvents::PROCESS,
      'event_class' => FormProcess::class,
      'tags' => Tag::READ | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['form_after_build'] = [
      'label' => 'After build form',
      'event_name' => FormEvents::AFTER_BUILD,
      'event_class' => FormAfterBuild::class,
      'tags' => Tag::READ | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['form_validate'] = [
      'label' => 'Validate form',
      'event_name' => FormEvents::VALIDATE,
      'event_class' => FormValidate::class,
      'tags' => Tag::READ | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['form_submit'] = [
      'label' => 'Submit form',
      'event_name' => FormEvents::SUBMIT,
      'event_class' => FormSubmit::class,
      'tags' => Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
    ];
    $actions['ief_build'] = [
      'label' => 'Build inline entity form',
      'event_name' => FormEvents::IEF_BUILD,
      'event_class' => InlineEntityFormBuild::class,
      'tags' => Tag::VIEW | Tag::RUNTIME | Tag::BEFORE,
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'form_id' => '',
      'entity_type_id' => '',
      'bundle' => '',
      'operation' => '',
    ]
    + ($this->getDerivativeId() === 'ief_build' ? [
      'parent_type_id' => '',
      'parent_bundle' => '',
      'field_name' => '',
    ] : [])
    + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['form_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict by form ID'),
      '#default_value' => $this->configuration['form_id'],
      '#description' => $this->t('The form ID can be mostly found in the HTML &lt;form&gt; element as "id" attribute.'),
    ];
    $form['entity_type_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict by entity type ID'),
      '#default_value' => $this->configuration['entity_type_id'],
      '#description' => $this->t('Example: <em>node, taxonomy_term, user</em>'),
    ];
    $form['bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict by entity bundle'),
      '#default_value' => $this->configuration['bundle'],
      '#description' => $this->t('Example: <em>article, tags</em>'),
    ];
    $form['operation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict by operation'),
      '#default_value' => $this->configuration['operation'],
      '#description' => $this->t('Example: <em>default, save, delete</em>'),
    ];
    if ($this->getDerivativeId() === 'ief_build') {
      $form['entity_type_id']['#weight'] = -100;
      $form['entity_type_id']['#description'] = $this->t('Example: <em>media, paragraph, storage</em>');
      unset($form['bundle']['#description']);
      $form['bundle']['#weight'] = -90;
      $form['form_id']['#title'] = $this->t('Parent: @option', [
        '@option' => $form['form_id']['#title'],
      ]);
      $form['operation']['#title'] = $this->t('Parent: @option', [
        '@option' => $form['operation']['#title'],
      ]);
      $form['parent_type_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Parent: @option', [
          '@option' => $this->t('Restrict by entity type ID'),
        ]),
        '#default_value' => $this->configuration['parent_type_id'],
        '#description' => $this->t('Example: <em>node, taxonomy_term, user</em>'),
        '#weight' => -80,
      ];
      $form['parent_bundle'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Parent: @option', [
          '@option' => $this->t('Restrict by entity bundle'),
        ]),
        '#default_value' => $this->configuration['parent_bundle'],
        '#description' => $this->t('Example: <em>article, tags</em>'),
        '#weight' => -70,
      ];
      $form['field_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Parent: @option', [
          '@option' => $this->t('Restrict by field name'),
        ]),
        '#default_value' => $this->configuration['field_name'],
        '#description' => $this->t('Example: <em>field_paragraphs</em>'),
        '#weight' => -60,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['form_id'] = $form_state->getValue('form_id');
    $this->configuration['entity_type_id'] = $form_state->getValue('entity_type_id');
    $this->configuration['bundle'] = $form_state->getValue('bundle');
    $this->configuration['operation'] = $form_state->getValue('operation');
    if ($this->getDerivativeId() === 'ief_build') {
      $this->configuration['parent_type_id'] = $form_state->getValue('parent_type_id');
      $this->configuration['parent_bundle'] = $form_state->getValue('parent_bundle');
      $this->configuration['field_name'] = $form_state->getValue('field_name');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();

    $wildcard = '';
    $form_ids = [];
    if (!empty($configuration['form_id'])) {
      foreach (explode(',', $configuration['form_id']) as $form_id) {
        $form_id = strtolower(trim(str_replace('-', '_', $form_id)));
        if ($form_id !== '') {
          $form_ids[] = $form_id;
        }
      }
    }
    if ($form_ids) {
      $wildcard .= implode(',', $form_ids);
    }
    else {
      $wildcard .= '*';
    }

    $wildcard .= ':';
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

    if ($this->getDerivativeId() === 'ief_build') {
      $wildcard .= ':';
      $parent_type_ids = [];
      if (!empty($configuration['parent_type_id'])) {
        foreach (explode(',', $configuration['parent_type_id']) as $entity_type_id) {
          $entity_type_id = strtolower(trim($entity_type_id));
          if ($entity_type_id !== '') {
            $parent_type_ids[] = $entity_type_id;
          }
        }
      }
      if ($parent_type_ids) {
        $wildcard .= implode(',', $parent_type_ids);
      }
      else {
        $wildcard .= '*';
      }

      $wildcard .= ':';
      $parent_bundles = [];
      if (!empty($configuration['parent_bundle'])) {
        foreach (explode(',', $configuration['parent_bundle']) as $bundle) {
          $bundle = strtolower(trim($bundle));
          if ($bundle !== '') {
            $parent_bundles[] = $bundle;
          }
        }
      }
      if ($parent_bundles) {
        $wildcard .= implode(',', $parent_bundles);
      }
      else {
        $wildcard .= '*';
      }

      $wildcard .= ':';
      $field_names = [];
      if (!empty($configuration['field_name'])) {
        foreach (explode(',', $configuration['field_name']) as $field_name) {
          $field_name = strtolower(trim($field_name));
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

    return $wildcard;
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event instanceof InlineEntityFormBuild) {
      [
        $w_form_ids,
        $w_entity_type_ids,
        $w_bundles,
        $w_operations,
        $w_parent_type_ids,
        $w_parent_bundles,
        $w_field_names,
      ] = explode(':', $wildcard);

      // Check for the specified parent type, if any.
      if (!self::doesApplyForWildcard($event, implode(':', [
        $w_form_ids,
        $w_parent_type_ids,
        $w_parent_bundles,
        $w_operations,
      ]))) {
        return FALSE;
      }

      // Check for the embedded entity type.
      $entity = $event->getEntity();
      if (($w_entity_type_ids !== '*') && !in_array($entity->getEntityTypeId(), explode(',', $w_entity_type_ids), TRUE)) {
        return FALSE;
      }
      if (($w_bundles !== '*') && !in_array($entity->bundle(), explode(',', $w_bundles), TRUE)) {
        return FALSE;
      }
      if (($w_field_names !== '*') && !in_array($event->getFieldName(), explode(',', $w_field_names), TRUE)) {
        return FALSE;
      }

      return TRUE;
    }
    return self::doesApplyForWildcard($event, $wildcard);
  }

  /**
   * Helper function to check wildcard application for all form events.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The event.
   * @param string $wildcard
   *   The wildcard.
   *
   * @return bool
   *   TRUE, if the event applies, FALSE otherwise.
   */
  private static function doesApplyForWildcard(Event $event, string $wildcard): bool {
    /** @var \Drupal\eca_form\Event\FormBase $event */
    [$w_form_ids, $w_entity_type_ids, $w_bundles, $w_operations] = explode(':', $wildcard);
    $form_object = $event->getFormState()->getFormObject();

    if ($w_form_ids !== '*') {
      $form_ids = [$form_object->getFormId()];
      if ($form_object instanceof BaseFormIdInterface) {
        $form_ids[] = $form_object->getBaseFormId();
      }

      if (empty(array_intersect($form_ids, explode(',', $w_form_ids)))) {
        return FALSE;
      }
    }

    if (!($form_object instanceof EntityFormInterface)) {
      // No entity form. This is OK if all 3 selectors are wildcards, otherwise
      // the configuration doesn't apply.
      return ($w_entity_type_ids === '*' && $w_bundles === '*' && $w_operations === '*');
    }

    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    if (($w_entity_type_ids !== '*') && !in_array($form_object->getEntity()->getEntityTypeId(), explode(',', $w_entity_type_ids), TRUE)) {
      return FALSE;
    }
    if (($w_bundles !== '*') && !in_array($form_object->getEntity()->bundle(), explode(',', $w_bundles), TRUE)) {
      return FALSE;
    }
    if (($w_operations !== '*') && !in_array($form_object->getOperation(), explode(',', $w_operations), TRUE)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The event.',
    classes: [
      InlineEntityFormBuild::class,
    ],
    properties: [
      new Token(name: 'delta', description: 'The delta of the entity reference in the parent field.'),
      new Token(name: 'entity', description: 'The form entity.'),
      new Token(name: 'entity_bundle', description: 'The bundle of the form entity.'),
      new Token(name: 'entity_id', description: 'The form entity ID, only available if the entity is not new.'),
      new Token(name: 'entity_type', description: 'The form entity type.'),
      new Token(name: 'field_name', description: 'The name of the field.'),
      new Token(name: 'parent', description: 'The parent entity, if one exists.'),
      new Token(name: 'parent_bundle', description: 'The bundle of the parent entity, if one exists.'),
      new Token(name: 'parent_id', description: 'The ID of the parent entity, if one exists and it is not new.'),
      new Token(name: 'parent_type', description: 'The type of the parent entity, if one exists'),
      new Token(name: 'widget', description: 'The form widget ID.'),
    ],
  )]
  protected function buildEventData(): array {
    $event = $this->event;
    $data = [];

    if ($event instanceof InlineEntityFormBuild) {
      $entity = $event->getEntity();
      $form_object = $event->getFormState()->getFormObject();
      $parent = $form_object instanceof EntityFormInterface ? $form_object->getEntity() : NULL;
      $data += [
        'entity' => $entity,
        'entity_type' => $entity->getEntityTypeId(),
        'entity_bundle' => $entity->bundle(),
        'parent' => $parent,
        'field_name' => $event->getFieldName(),
        'widget' => $event->getWidgetPluginId(),
        'delta' => $event->getDelta(),
      ];
      if (!$entity->isNew()) {
        $data['entity_id'] = $entity->id();
      }
      if ($parent) {
        $data['parent_type'] = $parent->getEntityTypeId();
        $data['parent_bundle'] = $parent->bundle();
        if (!$parent->isNew()) {
          $data['parent_id'] = $parent->id();
        }
      }
    }

    $data += parent::buildEventData();
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'entity',
    description: 'The form entity.',
    classes: [
      InlineEntityFormBuild::class,
    ],
  )]
  #[Token(
    name: 'ENTITY_TYPE',
    description: 'The form entity with the token name being the ID of the entity type.',
    classes: [
      InlineEntityFormBuild::class,
    ],
  )]
  #[Token(
    name: 'parent',
    description: 'The parent entity, if one exists.',
    classes: [
      InlineEntityFormBuild::class,
    ],
  )]
  public function getData(string $key): mixed {
    $event = $this->event;

    if ($event instanceof InlineEntityFormBuild) {
      $entity = $event->getEntity();
      if ($key === 'entity' || $key === $entity->getEntityTypeId() || $key === TokenServices::get()->getTokenType($entity)) {
        return $entity;
      }
      if ($key === 'parent') {
        $form_object = $event->getFormState()->getFormObject();
        return $form_object instanceof EntityFormInterface ? $form_object->getEntity() : NULL;
      }
    }

    return parent::getData($key);
  }

}
