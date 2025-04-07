<?php

namespace Drupal\simple_sitemap_engines\Form\Handler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Form\FormHelper;
use Drupal\simple_sitemap\Form\Handler\EntityFormHandlerBase as BaseEntityFormHandlerBase;
use Drupal\simple_sitemap\Manager\Generator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for altering an entity form.
 */
abstract class EntityFormHandlerBase extends BaseEntityFormHandlerBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * EntityFormHandlerBase constructor.
   *
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Helper class for working with forms.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(Generator $generator, EntityHelper $entity_helper, FormHelper $form_helper, ConfigFactoryInterface $config_factory) {
    parent::__construct($generator, $entity_helper, $form_helper);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('simple_sitemap.form_helper'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    $this->processFormState($form_state);
    $this->addSubmitHandlers($form, [$this, 'submitForm']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form): array {
    $settings = $this->getSettings();

    $form['simple_sitemap_index_now'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify IndexNow search engines of changes <em>by default</em>'),
      '#description' => $this->t('Send change notice to IndexNow compatible search engines right after submitting entity forms of this type.<br/>Changes include creating, deleting and updating of an entity. This setting can be overridden on the entity form.'),
      '#default_value' => (int) ($settings['index_now'] ?? 0),
      '#tree' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSettings(): array {
    if (!isset($this->settings)) {
      $this->settings = $this->configFactory
        ->get("simple_sitemap_engines.bundle_settings.$this->entityTypeId.$this->bundleName")
        ->get();
    }
    return $this->settings;
  }

}
