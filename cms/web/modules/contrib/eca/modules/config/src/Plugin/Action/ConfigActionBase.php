<?php

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for config-related actions.
 */
abstract class ConfigActionBase extends ConfigurableActionBase {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected ?ConfigFactoryInterface $configFactory = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setConfigFactory($container->get('config.factory'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?: $this->currentUser;
    if ($account->hasPermission('administer site configuration')) {
      return $return_as_object ? AccessResult::allowed()->cachePerPermissions() : TRUE;
    }
    return $return_as_object ? AccessResult::forbidden()->cachePerPermissions() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'config_name' => '',
      'config_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['config_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Config name'),
      '#description' => $this->t('The config name, for example <em>system.site</em>.'),
      '#default_value' => $this->configuration['config_name'],
      '#required' => TRUE,
      '#weight' => -90,
    ];
    $form['config_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Config key'),
      '#description' => $this->t('The config key, for example <em>page.front</em>. Leave empty to use the whole config.'),
      '#default_value' => $this->configuration['config_key'],
      '#weight' => -80,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['config_name'] = $form_state->getValue('config_name');
    $this->configuration['config_key'] = $form_state->getValue('config_key');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Get the config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function getConfigFactory(): ConfigFactoryInterface {
    if (!isset($this->configFactory)) {
      // @phpstan-ignore-next-line
      $this->configFactory = \Drupal::configFactory();
    }
    return $this->configFactory;
  }

  /**
   * Set the config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory): void {
    $this->configFactory = $config_factory;
  }

}
