<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Manager\EntityManager;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides form to manage entity bundles settings.
 */
class EntityBundlesForm extends SimpleSitemapFormBase {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityBundlesForm constructor.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    Generator $generator,
    Settings $settings,
    FormHelper $form_helper,
    EntityHelper $entity_helper,
    EntityManager $entity_manager,
    EntityTypeManagerInterface $entity_type_manager,
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
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_entity_bundles_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL): array {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      throw new NotFoundHttpException();
    }

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$this->entityHelper->supports($entity_type) || !$this->entityManager->entityTypeIsEnabled($entity_type_id)) {
      throw new NotFoundHttpException();
    }

    $form['#title'] = $this->t('Configure %label entity type', [
      '%label' => $entity_type->getLabel() ?: $entity_type_id,
    ]);

    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity_type_id,
    ];

    $form['bundles'] = [
      '#type' => 'vertical_tabs',
      '#access' => !$this->entityHelper->entityTypeIsAtomic($entity_type_id),
      '#attached' => ['library' => ['simple_sitemap/fieldsetSummaries']],
    ];

    foreach ($this->entityHelper->getBundleInfo($entity_type_id) as $bundle_name => $bundle_info) {
      $bundle_form = &$form['settings'][$bundle_name];

      $bundle_form = [
        '#type' => $form['bundles']['#access'] ? 'details' : 'container',
        '#title' => $this->entityHelper->getBundleLabel($entity_type_id, $bundle_name),
        '#attributes' => ['class' => ['simple-sitemap-fieldset']],
        '#group' => 'bundles',
        '#parents' => ['bundles', $bundle_name],
        '#tree' => TRUE,
      ];

      $bundle_form = $this->formHelper
        ->bundleSettingsForm($bundle_form, $entity_type_id, $bundle_name);
    }

    $form = $this->formHelper->regenerateNowForm($form);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type_id = $form_state->getValue('entity_type_id');
    $bundles = $form_state->getValue('bundles');

    foreach ($this->entityManager->getSitemaps() as $variant => $sitemap) {
      $this->entityManager->setSitemaps($sitemap);

      foreach ($bundles as $bundle_name => $settings) {
        if (isset($settings[$variant])) {
          $this->entityManager->setBundleSettings($entity_type_id, $bundle_name, $settings[$variant]);
        }
      }
    }

    parent::submitForm($form, $form_state);
  }

}
