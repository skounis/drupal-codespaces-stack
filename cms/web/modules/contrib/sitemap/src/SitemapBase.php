<?php

namespace Drupal\sitemap;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Sitemap plugin implementations.
 *
 * @ingroup sitemap
 */
abstract class SitemapBase extends PluginBase implements SitemapInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * An associative array containing the configured settings of the sitemap_map.
   *
   * @var array
   */
  public $settings = [];

  /**
   * A Boolean indicating whether this mapping is enabled.
   *
   * @var bool
   */
  public $enabled = FALSE;

  /**
   * The weight of this mapping compared to others in the sitemap.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * The name of the provider that owns this mapping.
   *
   * @var string
   */
  public $provider;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The global sitemap config.
   *
   * @var object
   */
  protected $sitemapConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->settings = $this->configuration['settings'];
    $this->provider = $this->configuration['provider'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    $plugin->currentUser = $container->get('current_user');
    $plugin->sitemapConfig = $container->get('config.factory')->get('sitemap.settings');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return ['module' => 'sitemap'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Provide a section title field for every mapping plugin.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->settings['title'],
      '#description' => $this->t('If you do not wish to display a title, leave this field blank.'),
      '#weight' => -10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->baseConfigurationDefaults(),
      $this->defaultConfiguration(),
      $configuration
    );
    $this->enabled = $this->configuration['enabled'];
    $this->weight = $this->configuration['weight'];
  }

  /**
   * Returns generic default configuration for sitemap plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function baseConfigurationDefaults() {
    return [
      'base_plugin' => $this->getBaseId(),
      'id' => $this->getPluginId(),
      'provider' => $this->pluginDefinition['provider'],
      'enabled' => $this->pluginDefinition['enabled'] ?? FALSE,
      'weight' => $this->pluginDefinition['weight'] ?? 0,
      'settings' => $this->pluginDefinition['settings'] ?? [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

}
