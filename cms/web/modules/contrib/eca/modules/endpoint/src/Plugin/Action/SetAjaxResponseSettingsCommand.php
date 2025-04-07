<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Add settings to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_settings",
 *   label = @Translation("Ajax Response: settings"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseSettingsCommand extends ResponseAjaxCommandBase {

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    try {
      $settings = $this->yamlParser->parse((string) $this->tokenService->replaceClear($this->configuration['settings'])) ?? [];
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing settings in action "@id" as YAML format, but parsing failed.', [
        '@id' => $this->pluginDefinition['id'],
      ]);
      $settings = [];
    }
    $merge = (bool) $this->configuration['merge'];
    return new SettingsCommand($settings, $merge);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'settings' => '',
      'merge' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Settings'),
      '#description' => $this->t('An array of key/value pairs of JavaScript settings in YAML format.'),
      '#default_value' => $this->configuration['settings'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['merge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Merge'),
      '#description' => $this->t('Whether the settings should be merged into the global drupalSettings.'),
      '#default_value' => $this->configuration['merge'],
      '#weight' => -40,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['settings'] = (string) $form_state->getValue('settings');
    $this->configuration['merge'] = (bool) $form_state->getValue('merge');
    parent::submitConfigurationForm($form, $form_state);
  }

}
