<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Completion plugin configuration.
 */
class AiCKEditor extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The default configuration for this plugin.
   *
   * @var string[][]
   */
  const DEFAULT_CONFIGURATION = [
    'dialog' => [
      'autoresize' => 'min-width: 600px',
      'height' => '750',
      'width' => '900',
      'dialog_class' => 'ai-ckeditor-modal',
    ],
    'plugins' => [],
  ];

  /**
   * The AI CKEditor plugin manager.
   *
   * @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager
   */
  protected $pluginManager;

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * AI CKEditor plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $plugin_manager
   *   The AI CKEditor plugin manager.
   * @param \Drupal\ai\AiProviderPluginManager $provider_manager
   *   The AI provider manager.
   */
  final public function __construct(array $configuration, string $plugin_id, CKEditor5PluginDefinition $plugin_definition, AiCKEditorPluginManager $plugin_manager, AiProviderPluginManager $provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManager = $plugin_manager;
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $definitions = $this->pluginManager->getDefinitions();
    if (!$definitions) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No AI CKEditor plugins were detected.'),
      ];
      return $form;
    }

    $form['dialog'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#open' => TRUE,
      '#title' => $this->t('Modal dialog options'),
    ];

    $form['dialog']['autoresize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auto-resize dialog'),
      '#description' => $this->t('Auto-resize the dialog when a modal is loaded, based on a CSS value. Leave blank to disable auto-resize. An example value for CSS could be: "min-width: 600px"'),
      '#default_value' => $this->configuration['dialog']['autoresize'] ?? FALSE,
    ];

    $form['dialog']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('A pixel or percent value of what the height of the modal should be. For pixel value, do not include "px".'),
      '#default_value' => $this->configuration['dialog']['height'] ?? 750,
    ];

    $form['dialog']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('A pixel or percent value of what the width of the modal should be. For pixel value, do not include "px".'),
      '#default_value' => $this->configuration['dialog']['width'] ?? 900,
    ];

    $form['dialog']['dialog_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialog CSS class'),
      '#description' => $this->t('A CSS class or classes to apply to the modal dialog.'),
      '#default_value' => $this->configuration['dialog']['dialog_class'] ?? 'ai-ckeditor-modal',
    ];

    $form['plugins'] = [];

    foreach ($definitions as $plugin_id => $definition) {
      $form['plugins'][$plugin_id] = [
        '#type' => 'details',
        '#tree' => TRUE,
        '#open' => FALSE,
        '#title' => $definition['label'],
      ];

      $form['plugins'][$plugin_id]['description'] = [
        '#markup' => $definition['description'],
      ];

      $form['plugins'][$plugin_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $this->configuration['plugins'][$plugin_id]['enabled'] ?? FALSE,
        '#description' => $this->t('Enable this editor feature.'),
      ];

      $subform = $form['config']['plugin_config'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $instance = $this->pluginManager->createInstance($plugin_id, $this->configuration['plugins'][$plugin_id] ?? []);
      $form['plugins'][$plugin_id] = $form['plugins'][$plugin_id] + $instance->buildConfigurationForm($subform, $subform_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $definitions = $this->pluginManager->getDefinitions();
    // Let the plugins validate their own configuration.
    foreach ($definitions as $plugin_id => $definition) {
      $subform = $form['plugins'][$plugin_id] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $instance = $this->pluginManager->createInstance($plugin_id, $this->configuration['plugins'][$plugin_id] ?? []);
      $instance->validateConfigurationForm($subform, $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!empty($values['dialog'])) {
      $this->configuration['dialog']['autoresize'] = (is_string($values["dialog"]["autoresize"]) && !empty($values["dialog"]["autoresize"])) ? $values["dialog"]["autoresize"] : FALSE;
      $this->configuration['dialog']['height'] = is_string($values["dialog"]["height"]) ? $values["dialog"]["height"] : $this->defaultConfiguration()['height'];
      $this->configuration['dialog']['width'] = is_string($values["dialog"]["width"]) ? $values["dialog"]["width"] : $this->defaultConfiguration()['width'];
      $this->configuration['dialog']['dialog_class'] = is_string($values["dialog"]["dialog_class"]) ? $values["dialog"]["dialog_class"] : $this->defaultConfiguration()['dialog_class'];
    }

    if (!empty($values['plugins'])) {
      foreach ($values['plugins'] as $plugin_id => $plugin) {
        $subform = $form['plugins'][$plugin_id] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $instance = $this->pluginManager->createInstance($plugin_id);
        $instance->submitConfigurationForm($form, $subform_state);
        $this->configuration['plugins'][$plugin_id] = $instance->getConfiguration();
        $this->configuration['plugins'][$plugin_id]['enabled'] = (bool) $plugin['enabled'];
      }
    }

    $this->setConfiguration($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $ai_ckeditor_dialog_url = Url::fromRoute('ai_ckeditor.dialog')
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['ai_ckeditor_ai']['dialogURL'] = $ai_ckeditor_dialog_url;

    $config = $this->getConfiguration();

    if (!empty($config["dialog"])) {
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['autoResize'] = (is_string($config["dialog"]["autoresize"]) && !empty($config["dialog"]["autoresize"])) ? $config["dialog"]["autoresize"] : FALSE;
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['height'] = is_string($config["dialog"]["height"]) ? $config["dialog"]["height"] : $this->defaultConfiguration()['height'];
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['width'] = is_string($config["dialog"]["width"]) ? $config["dialog"]["width"] : $this->defaultConfiguration()['width'];
      $static_plugin_config['ai_ckeditor_ai']['dialogSettings']['dialogClass'] = is_string($config["dialog"]["dialog_class"]) ? $config["dialog"]["dialog_class"] : $this->defaultConfiguration()['dialog_class'];
    }

    $all_disabled = TRUE;
    foreach ($config['plugins'] as $plugin_id => $plugin) {
      $definition = $this->pluginManager->getDefinition($plugin_id);
      if ($all_disabled && $plugin['enabled']) {
        $all_disabled = FALSE;
      }

      // Load the plugin.
      $instance = $this->pluginManager->createInstance($plugin_id, $plugin);
      // Check the editors each plugin gives back.
      foreach ($instance->availableEditors() as $id => $label) {
        $static_plugin_config['ai_ckeditor_ai']['plugins'][$id] = [
          'enabled' => $plugin['enabled'],
          'provider' => $plugin['provider'] ?? NULL,
          'meta' => [
            'label' => $label,
            'id' => $id,
          ],
        ];
      }

    }

    foreach ($static_plugin_config['ai_ckeditor_ai']['plugins'] as $plugin_id => $plugin) {
      if ($plugin_id == 'ai_ckeditor_help') {
        unset($static_plugin_config['ai_ckeditor_ai']['plugins'][$plugin_id]);
        $static_plugin_config['ai_ckeditor_ai']['plugins'][$plugin_id] = $plugin;
        break;
      }
    }

    // Hide if nothing is enabled.
    return $all_disabled ? [] : $static_plugin_config;
  }

}
