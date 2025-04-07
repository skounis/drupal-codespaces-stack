<?php

namespace Drupal\simple_sitemap_engines\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Form\FormHelper as BaseFormHelper;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Settings;
use Drupal\simple_sitemap_engines\Form\Handler\BundleEntityFormHandler;
use Drupal\simple_sitemap_engines\Form\Handler\EntityFormHandler;

/**
 * Slightly altered version of the base form helper.
 */
class FormHelper extends BaseFormHelper {

  protected const ENTITY_FORM_HANDLER = EntityFormHandler::class;
  protected const BUNDLE_ENTITY_FORM_HANDLER = BundleEntityFormHandler::class;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * FormHelper constructor.
   *
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Proxy for the current user account.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    Generator $generator,
    Settings $settings,
    EntityHelper $entity_helper,
    AccountProxyInterface $current_user,
    ClassResolverInterface $class_resolver,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($generator, $settings, $entity_helper, $current_user, $class_resolver);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function formAlterAccess(): bool {
    $access = parent::formAlterAccess();

    return $this->configFactory->get('simple_sitemap_engines.settings')->get('index_now_enabled')
      && ($access || $this->currentUser->hasPermission('index entity on save'));
  }

  /**
   * Alters the specific form (simple_sitemap_entities_form).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @see \Drupal\simple_sitemap\Form\EntitiesForm
   * @see simple_sitemap_engines_form_simple_sitemap_entities_form_alter()
   */
  public function entitiesFormAlter(array &$form) {
    if (!$this->formAlterAccess()) {
      return;
    }

    $table = &$form['entity_types'];
    $offset = array_search('bundles', array_keys($table['#header']), TRUE) + 1;
    array_splice($table['#header'], $offset, 0, ['index_now' => $this->t('IndexNow')]);

    // Add column with IndexNow status.
    foreach (Element::children($table) as $entity_type_id) {
      $element = ['#markup' => ''];

      if ($table[$entity_type_id]['enabled']['#default_value']) {
        $bundles = [];

        foreach ($this->configFactory->listAll("simple_sitemap_engines.bundle_settings.$entity_type_id.") as $name) {
          if ($this->configFactory->get($name)->get('index_now')) {
            $name_parts = explode('.', $name);

            $bundles[] = $this->entityHelper
              ->getBundleLabel($entity_type_id, end($name_parts));
          }
        }

        $element['#markup'] = $bundles
          ? '<em>' . implode(', ', $bundles) . '</em>'
          : $this->t('Excluded');
      }

      array_splice($table[$entity_type_id], $offset, 0, ['index_now' => $element]);
    }
  }

  /**
   * Alters the specific form (simple_sitemap_entity_bundles_form).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @see \Drupal\simple_sitemap\Form\EntityBundlesForm
   * @see simple_sitemap_engines_form_simple_sitemap_entity_bundles_form_alter()
   */
  public function entityBundlesFormAlter(array &$form) {
    if (!$this->formAlterAccess()) {
      return;
    }

    foreach ($form['settings'] as $bundle_name => &$bundle_form) {
      $bundle_form = $this->bundleSettingsForm($bundle_form, $form['entity_type_id']['#value'], $bundle_name);
      $bundle_form['simple_sitemap_index_now']['#tree'] = TRUE;
    }

    $form['#submit'][] = [$this, 'entityBundlesFormSubmit'];
  }

  /**
   * Form submission handler (simple_sitemap_entity_bundles_form).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\simple_sitemap\Form\EntityBundlesForm
   */
  public function entityBundlesFormSubmit(array &$form, FormStateInterface $form_state) {
    $entity_type_id = $form_state->getValue('entity_type_id');

    foreach ($form_state->getValue('bundles') as $bundle_name => $settings) {
      $this->configFactory
        ->getEditable("simple_sitemap_engines.bundle_settings.$entity_type_id.$bundle_name")
        ->set('index_now', $settings['simple_sitemap_index_now'])
        ->save();
    }
  }

}
