<?php

namespace Drupal\eca_log\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Write a log message.
 *
 * @Action(
 *   id = "eca_write_log_message",
 *   label = @Translation("Log Message"),
 *   description = @Translation("Writes a log message into the given type with the given severity."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class LogMessage extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerChannelFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->loggerChannelFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $channel = $this->tokenService->replaceClear($this->configuration['channel']);
    if (empty($channel)) {
      $channel = 'eca';
    }
    $severity = $this->configuration['severity'];
    if ($severity === '_eca_token') {
      $severity = $this->getTokenValue('severity', '');
    }
    $severity = (int) $severity;
    $message = $this->configuration['message'];
    $context = [];
    foreach ($this->tokenService->scan($message) as $type => $tokens) {
      $replacements = $this->tokenService->generate($type, $tokens, [], ['clear' => TRUE], new BubbleableMetadata());
      foreach ($replacements as $original_token => $replacement_value) {
        $context_argument = '%token__' . mb_substr(str_replace(':', '_', $original_token), 1, -1);
        $message = str_replace($original_token, $context_argument, $message);
        $context[$context_argument] = $replacement_value;
      }
    }
    $this->loggerChannelFactory->get($channel)->log($severity, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'channel' => '',
      'severity' => '',
      'message' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['channel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#default_value' => $this->configuration['channel'],
      '#description' => $this->t('The name of the logger type, the message should be logged to.'),
      '#weight' => -30,
    ];
    $form['severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Severity'),
      '#description' => $this->t('The severity of the log message.'),
      '#default_value' => $this->configuration['severity'],
      '#options' => RfcLogLevel::getLevels(),
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#description' => $this->t('The message, which should be logged.'),
      '#default_value' => $this->configuration['message'],
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['channel'] = $form_state->getValue('channel');
    $this->configuration['severity'] = $form_state->getValue('severity');
    $this->configuration['message'] = $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

}
