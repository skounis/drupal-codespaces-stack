<?php

declare(strict_types=1);

namespace Drupal\trash\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Trash settings for this site.
 */
class TrashSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type bundle info.
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'trash_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['trash.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('trash.settings');
    $enabled_entity_types = $config->get('enabled_entity_types') ?? [];
    $unsupported_entity_types = $this->getUnsupportedEntityTypes();

    // Get all applicable entity types.
    $applicable_entity_types = array_map(
      fn (EntityTypeInterface $entity_type): string => (string) $entity_type->getLabel(),
      array_filter(
        $this->entityTypeManager->getDefinitions(),
        fn (EntityTypeInterface $entity_type): bool =>
          is_subclass_of($entity_type->getStorageClass(), SqlEntityStorageInterface::class)
          && !in_array($entity_type->id(), $unsupported_entity_types, TRUE),
      )
    );
    asort($applicable_entity_types);

    $form['enabled_entity_types'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Enabled entity types'),
      '#tree' => TRUE,
    ];

    foreach ($applicable_entity_types as $entity_type_id => $entity_type_label) {
      /** @var \Drupal\Core\Field\BaseFieldDefinition[] $field_definitions */
      $field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      $form['enabled_entity_types'][$entity_type_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type_label,
        '#default_value' => isset($field_definitions['deleted']) && isset($enabled_entity_types[$entity_type_id]),
        '#disabled' => isset($field_definitions['deleted']) && ($field_definitions['deleted']->getProvider() !== 'trash'),
      ];

      $bundles = array_map(
        fn (array $bundle): string => (string) $bundle['label'],
        $this->entityTypeBundleInfo->getBundleInfo($entity_type_id)
      );

      if (count($bundles) > 1) {
        asort($bundles);
        $form['enabled_entity_types'][$entity_type_id]['bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Bundles'),
          '#description' => $this->t('If none are selected, all are allowed.'),
          '#options' => $bundles,
          '#default_value' => $enabled_entity_types[$entity_type_id] ?? [],
          '#states' => [
            'visible' => [
              ':input[name="enabled_entity_types[' . $entity_type_id . '][enabled]"]' => ['checked' => TRUE],
            ],
          ],
          '#attributes' => ['class' => ['trash--bundles']],
        ];
      }
    }

    $form['auto_purge'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Auto-purge settings'),
      '#tree' => TRUE,
    ];
    $form['auto_purge']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable auto-purge'),
      '#description' => $this->t('Beware: this will permanently delete entities in the trash bin after the configured time period.'),
      '#default_value' => $config->get('auto_purge.enabled'),
    ];
    $form['auto_purge']['after'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auto-purge after entities have been in the trash bin for longer than this time'),
      '#description' => $this->t("The time period should be specified as '30 days', '15 days, 12 hours', etc."),
      '#default_value' => $config->get('auto_purge.after'),
      '#config_target' => 'trash.settings:auto_purge.after',
      '#states' => [
        'visible' => [
          ':input[name="auto_purge[enabled]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="auto_purge[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['compact_overview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Compact overview'),
      '#config_target' => 'trash.settings:compact_overview',
      '#description' => $this->t('Simplify the <a href=":url">Trash overview page</a> when there are many entity types enabled.', [
        ':url' => Url::fromRoute('trash.admin_content_trash')->toString(),
      ]),
    ];

    $form['#attached']['library'][] = 'trash/trash.admin';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $auto_purge = $form_state->getValue('auto_purge');
    if (!empty($auto_purge['enabled']) && empty($auto_purge['after'])) {
      $form_state->setErrorByName('auto_purge][after', $this->t('Auto-purge time period is required.'));
    }
    elseif (empty($auto_purge['enabled'])) {
      $form_state->unsetValue(['auto_purge', 'after']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('trash.settings');
    $form_state->cleanValues();

    foreach ($form_state->getValues() as $key => $value) {
      if ($key == 'enabled_entity_types') {
        $enabled_entity_types = [];
        foreach ($value as $entity_type_id => $entity_type_config) {
          /** @var \Drupal\Core\Field\BaseFieldDefinition[] $field_definitions */
          $field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
          // Verify that the entity type is enabled and that it is not defined
          // or defined by us before adding it to the configuration, so that
          // we do not store an entity type that cannot be enabled or disabled.
          if ($entity_type_config['enabled'] && (!isset($field_definitions['deleted']) || ($field_definitions['deleted']->getProvider() === 'trash'))) {
            $enabled_entity_types[$entity_type_id] = array_keys(array_filter($entity_type_config['bundles'] ?? []));
          }
        }
        $value = $enabled_entity_types;
      }
      $config->set($key, $value);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns an array of entity types that are not supported by Trash.
   */
  protected function getUnsupportedEntityTypes(): array {
    // Disallow enabling trash on entity types that haven't been tested enough.
    $unsupported_entity_types = [
      'comment',
      'taxonomy_term',
      'path_alias',
      'user',
      'workspace',
    ];

    if ($this->entityTypeManager->hasDefinition('menu_link_content')) {
      // Custom menu links can be deleted if there's a module which allows
      // changing the hierarchy in pending revisions (e.g. wse_menu).
      $menu_link_content = $this->entityTypeManager->getDefinition('menu_link_content');
      $constraints = $menu_link_content->getConstraints();
      if (isset($constraints['MenuTreeHierarchy'])) {
        $unsupported_entity_types = array_merge($unsupported_entity_types, ['menu_link_content']);
      }
    }

    return $unsupported_entity_types;
  }

}
