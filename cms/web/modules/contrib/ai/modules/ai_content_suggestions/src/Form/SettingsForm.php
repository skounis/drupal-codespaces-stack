<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Ai content suggestions settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager $pluginManager
   *   The AI Content Suggestions Plugin Manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiContentSuggestionsPluginManager $pluginManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.ai_content_suggestions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_content_suggestions_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_content_suggestions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['introduction'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Below is a list of the available plugins you can use to analyze your content.'),
    ];

    foreach ($this->pluginManager->getDefinitions() as $id => $config) {
      /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
      if ($plugin = $this->pluginManager->createInstance($id, $config)) {
        if ($plugin->isAvailable()) {
          $plugin->buildSettingsForm($form);
        }
      }
    }

    // If new suggestion plugins are added, or new providers make existing
    // plugins available, we want to rebuild the form.
    $form['#cache']['contexts'][] = 'ai_content_suggestions_plugins';
    $form['#cache']['contexts'][] = 'ai_providers';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [];

    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      $value = $form_state->getValue($id);
      if ($value[$id . '_enabled']) {
        $values[$id] = $value[$id . '_model'];
      }
      /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
      if ($plugin = $this->pluginManager->createInstance($id, $definition)) {
        if ($plugin->isAvailable()) {
          if (method_exists($plugin, 'saveSettingsForm')) {
            $plugin->saveSettingsForm($form, $form_state);
          }
        }
      }
    }

    $this->config('ai_content_suggestions.settings')
      ->set('plugins', $values)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
