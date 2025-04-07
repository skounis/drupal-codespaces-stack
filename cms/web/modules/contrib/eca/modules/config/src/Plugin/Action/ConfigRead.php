<?php

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to read configuration.
 *
 * @Action(
 *   id = "eca_config_read",
 *   label = @Translation("Config: read"),
 *   description = @Translation("Read configuration and store it as a token."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class ConfigRead extends ConfigActionBase {

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|null
   */
  protected ?TypedConfigManagerInterface $typedConfigManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setTypedConfigManager($container->get('config.typed'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $token = $this->tokenService;
    $token_name = $this->configuration['token_name'];
    $config_name = $token->replace($this->configuration['config_name']);
    $config_key = $this->configuration['config_key'] !== '' ? (string) $token->replace($this->configuration['config_key']) : '';
    $include_overridden = $this->configuration['include_overridden'];
    $config_factory = $this->getConfigFactory();

    $config = $include_overridden ? $config_factory->get($config_name) : $config_factory->getEditable($config_name);
    if ($include_overridden) {
      // No usage of typed config when overridden values shall be included.
      // This prevents the DTO from accidentally saving overridden values.
      $value = $config->get($config_key);
    }
    else {
      $value = $this->typedConfigManager->createFromNameAndData($config->getName(), $config->get());
      if ($config_key !== '') {
        $key_parts = explode('.', $config_key);
        while (($key = array_shift($key_parts)) !== NULL) {
          if (is_iterable($value)) {
            foreach ($value as $k => $element) {
              if ($k === $key) {
                $value = $element;
                continue 2;
              }
            }
          }
          $value = NULL;
          break;
        }
      }
    }

    $token->addTokenData($token_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'include_overridden' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['include_overridden'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include overridden'),
      '#description' => $this->t('Whether to apply module and settings.php overrides to values.'),
      '#default_value' => $this->configuration['include_overridden'],
      '#weight' => -70,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The targeted configuration value will be loaded into this specified token.'),
      '#weight' => -60,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['include_overridden'] = !empty($form_state->getValue('include_overridden'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the typed config manager.
   *
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $manager
   *   The manager.
   */
  public function setTypedConfigManager(TypedConfigManagerInterface $manager): void {
    $this->typedConfigManager = $manager;
  }

}
