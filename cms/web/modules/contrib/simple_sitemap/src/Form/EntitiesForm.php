<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Manager\EntityManager;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Settings;

/**
 * Provides form to manage entity settings.
 */
class EntitiesForm extends SimpleSitemapFormBase {

  /**
   * Helper class for working with entities.
   *
   * @var \Drupal\simple_sitemap\Entity\EntityHelper
   */
  protected $entityHelper;

  /**
   * The simple_sitemap.entity_manager service.
   *
   * @var \Drupal\simple_sitemap\Manager\EntityManager
   */
  protected $entityManager;

  /**
   * EntitiesForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Helper class for working with forms.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\simple_sitemap\Manager\EntityManager $entity_manager
   *   The simple_sitemap.entity_manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    Generator $generator,
    Settings $settings,
    FormHelper $form_helper,
    EntityHelper $entity_helper,
    EntityManager $entity_manager,
  ) {
    parent::__construct(
      $config_factory,
      $typedConfigManager,
      $generator,
      $settings,
      $form_helper
    );
    $this->entityHelper = $entity_helper;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_entities_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $table = &$form['entity_types'];

    $table = [
      '#type' => 'table',
      '#header' => [
        'type' => $this->t('Entity type'),
        'bundles' => $this->t('Indexed bundles'),
        'enabled' => $this->t('Enabled'),
        'operations' => $this->t('Operations'),
      ],
      '#empty' => $this->t('No supported entity types available.'),
      '#attached' => ['library' => ['simple_sitemap/sitemapEntities']],
    ];

    $entity_types = $this->entityHelper->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type_id => &$entity_type) {
      $entity_type = $entity_type->getLabel() ?: $entity_type_id;
    }
    natcasesort($entity_types);

    /** @var string|\Drupal\Core\StringTranslation\TranslatableMarkup $label */
    foreach ($entity_types as $entity_type_id => $label) {
      $is_enabled = $this->entityManager->entityTypeIsEnabled($entity_type_id);

      $table[$entity_type_id]['type'] = [
        '#markup' => '<strong>' . $label . '</strong>',
      ];

      $table[$entity_type_id]['bundles'] = [
        '#type' => 'item',
        '#input' => FALSE,
        '#markup' => $this->getIndexedBundlesString($entity_type_id),
      ];

      $table[$entity_type_id]['enabled'] = [
        '#type' => 'checkbox',
        '#default_value' => $is_enabled,
        '#parents' => ['entity_types', $entity_type_id],
      ];

      $table[$entity_type_id]['operations'] = [
        '#type' => 'operations',
        '#access' => $is_enabled,
        '#links' => [
          'display_edit' => [
            'title' => $this->t('Configure'),
            'url' => Url::fromRoute('simple_sitemap.entity_bundles', ['entity_type_id' => $entity_type_id], [
              'query' => ['destination' => Url::fromRoute('<current>')->toString()],
            ]),
          ],
        ],
      ];

      if ($is_enabled) {
        $table[$entity_type_id]['#attributes']['class'][] = 'color-success';

        if ($this->hasIndexedBundles($entity_type_id)) {
          $table[$entity_type_id]['#attributes']['class'][] = 'protected';
        }
      }
    }

    $form = $this->formHelper->regenerateNowForm($form);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_types = $form_state->getValue('entity_types');

    foreach ($entity_types as $entity_type_id => $enabled) {
      if ($enabled) {
        $this->entityManager->enableEntityType($entity_type_id);
      }
      else {
        $this->entityManager->disableEntityType($entity_type_id);
      }
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Gets indexed bundles.
   *
   * @return array
   *   Indexed bundles data.
   */
  protected function getIndexedBundles(): array {
    static $indexed_bundles;

    if ($indexed_bundles === NULL) {
      $indexed_bundles = [];

      foreach ($this->entityManager->setSitemaps()->getAllBundleSettings() as $variant => $entity_types) {
        $sitemap_label = SimpleSitemap::load($variant)->label();

        foreach ($entity_types as $entity_type_id => $bundles) {
          foreach ($bundles as $bundle_name => $bundle_settings) {
            if ($bundle_settings['index']) {
              $indexed_bundles[$entity_type_id][$bundle_name]['sitemaps'][] = $sitemap_label;
              $indexed_bundles[$entity_type_id][$bundle_name]['bundle_label'] = $this->entityHelper->getBundleLabel($entity_type_id, $bundle_name);
            }
          }
        }
      }
    }

    return $indexed_bundles;
  }

  /**
   * Determines whether the given entity type has indexed bundles.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if the given entity type has indexed bundles, FALSE otherwise.
   */
  protected function hasIndexedBundles(string $entity_type_id): bool {
    return !empty($this->getIndexedBundles()[$entity_type_id]);
  }

  /**
   * Gets a string representation of indexed bundles for the given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   A string representation of indexed bundles for the given entity type.
   */
  protected function getIndexedBundlesString(string $entity_type_id) {
    if (!$this->entityManager->entityTypeIsEnabled($entity_type_id)) {
      return '';
    }
    if (!$this->hasIndexedBundles($entity_type_id)) {
      return $this->t('Excluded from all sitemaps');
    }

    foreach ($this->getIndexedBundles()[$entity_type_id] as $bundle_data) {
      $pieces[] = $this->t('%bundle_label <span class="description">(sitemaps: %sitemaps)</span>', [
        '%bundle_label' => $bundle_data['bundle_label'],
        '%sitemaps' => implode(', ', $bundle_data['sitemaps']),
      ]);
    }

    return implode('<br />', $pieces ?? []);
  }

}
