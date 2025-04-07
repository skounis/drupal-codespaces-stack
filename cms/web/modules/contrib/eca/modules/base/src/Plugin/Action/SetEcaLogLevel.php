<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\eca\ConfigurableLoggerChannel;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\CleanupInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to add an item to a list.
 *
 * @Action(
 *   id = "eca_set_eca_log_level",
 *   label = @Translation("Set ECA log level"),
 *   description = @Translation("The log level for ECA can be changed temporarily for the processing within the current event."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetEcaLogLevel extends ConfigurableActionBase implements CleanupInterface {

  use PluginFormTrait;

  /**
   * A flag indicating whether an account switch was done.
   *
   * @var bool
   */
  protected bool $logLevelChanged = FALSE;

  /**
   * The configured log level before being changed.
   *
   * @var int
   */
  protected int $configuredLogLevel;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configuredLogLevel = $container->get('config.factory')->get('eca.settings')->get('log_level');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if ($this->logger instanceof ConfigurableLoggerChannel) {
      $logLevel = $this->configuration['log_level'];
      if ($logLevel === '_eca_token') {
        $logLevel = $this->getTokenValue('log_level', (string) $this->configuredLogLevel);
      }
      $this->logger->updateLogLevel((int) $logLevel);
      $this->logLevelChanged = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupAfterSuccessors(): void {
    if ($this->logLevelChanged && $this->logger instanceof ConfigurableLoggerChannel) {
      $this->logger->updateLogLevel($this->configuredLogLevel);
      $this->logLevelChanged = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'log_level' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['log_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Log level'),
      '#options' => RfcLogLevel::getLevels(),
      '#default_value' => $this->configuration['log_level'],
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['log_level'] = $form_state->getValue('log_level');
  }

}
