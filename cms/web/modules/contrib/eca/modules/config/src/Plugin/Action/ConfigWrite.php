<?php

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to write configuration.
 *
 * @Action(
 *   id = "eca_config_write",
 *   label = @Translation("Config: write"),
 *   description = @Translation("Writes into configuration from a token."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class ConfigWrite extends ConfigActionBase {

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
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $token = $this->tokenService;
    $config_name = $token->replace($this->configuration['config_name']);
    $config_key = $this->configuration['config_key'] !== '' ? (string) $token->replace($this->configuration['config_key']) : '';
    $config_value = $this->configuration['config_value'];
    if ($this->configuration['use_yaml']) {
      try {
        $config_value = $this->yamlParser->parse($config_value);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a config value in action "eca_config_write" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $config_value = $token->getOrReplace($config_value);
      if ($config_value instanceof DataTransferObject) {
        $config_value = $config_value->count() ? $config_value->toArray() : $config_value->getString();
      }
      elseif ($config_value instanceof ComplexDataInterface) {
        $config_value = $config_value->toArray();
      }
      elseif ($config_value instanceof TypedDataInterface) {
        $config_value = $config_value->getValue();
      }
      if ($config_value instanceof EntityInterface) {
        $config_value = $config_value->toArray();
      }
    }
    $config_factory = $this->getConfigFactory();

    $config = $config_factory->getEditable($config_name);
    $current_value = $config->get($config_key);

    // Change the configuration value only when the new config value differs.
    if ($current_value !== $config_value) {
      if ($config_key === '') {
        $config->setData($config_value);
      }
      else {
        $config->set($config_key, $config_value);
      }
      if ($this->configuration['save_config']) {
        $config->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'config_value' => '',
      'use_yaml' => FALSE,
      'save_config' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['config_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Config value'),
      '#description' => $this->t('The value to set.'),
      '#default_value' => $this->configuration['config_value'],
      '#weight' => -70,
      '#eca_token_replacement' => TRUE,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above config value as YAML format'),
      '#description' => $this->t('Nested data can be set using YAML format, for example <em>front: /node</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>front: "[myurl:path]"</em>'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -60,
    ];
    $form['save_config'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save configuration'),
      '#default_value' => $this->configuration['save_config'],
      '#weight' => -50,
      '#description' => $this->t('Save the given config to the Drupal database.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['config_value'] = $form_state->getValue('config_value');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->configuration['save_config'] = !empty($form_state->getValue('save_config'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

}
