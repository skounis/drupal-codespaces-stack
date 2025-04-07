<?php

namespace Drupal\ai_eca\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AI-related actions.
 */
abstract class AiActionBase extends ConfigurableActionBase {

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->aiProvider = $container->get('ai.provider');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowed();

    // Validate that a correct model has been selected.
    if (count($this->getModelData()) !== 2) {
      $access = AccessResult::forbidden(sprintf('The model "%s" is not configured for this operation (%s).', $this->configuration['model'], $this->getOperationType()));
    }

    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'model' => '',
      'token_input' => '',
      'token_result' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $models = $this->aiProvider->getSimpleProviderModelOptions($this->getOperationType());

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#options' => $models,
      '#default_value' => $this->configuration['model'],
      '#required' => TRUE,
      '#description' => $this->t('The applicable model for this operation type.'),
    ];

    $form['token_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token input'),
      '#default_value' => $this->configuration['token_input'],
      '#description' => $this->t('The data input for AI.'),
      '#eca_token_reference' => TRUE,
    ];

    $form['token_result'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token result'),
      '#default_value' => $this->configuration['token_result'],
      '#description' => $this->t('The response from AI will be stored into the token result field to be used in future steps.'),
      '#eca_token_reference' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    // Validate that a correct model has been selected.
    $model = explode('__', $form_state->getValue('model'));
    if (count($model) !== 2) {
      $form_state->setErrorByName('model', $this->t('The model "@model" is not configured for this operation (@operation).', [
        '@model' => $form_state->getValue('model'),
        '@operation' => $this->getOperationType(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['model'] = $form_state->getValue('model');
    $this->configuration['token_input'] = $form_state->getValue('token_input');
    $this->configuration['token_result'] = $form_state->getValue('token_result');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();

    $modelData = $this->getModelData();
    $definition = $this->aiProvider->getDefinition($modelData['provider_id']);
    $dependencies['module'][] = $definition['provider'];

    return $dependencies;
  }

  /**
   * Get the data of the configured model.
   *
   * @param string|null $source
   *   The optional source string.
   *
   * @return array{provider_id: string, model_id: string}
   *   Returns an array containing the provider ID and the model ID.
   */
  protected function getModelData(?string $source = NULL): array {
    $data = explode('__', $source ?? $this->configuration['model']);
    if (count($data) !== 2) {
      $source = $source ?? $this->configuration['model'];
      throw new \InvalidArgumentException('Given source "' . $source . '" is not valid. Could not determine provider and model.');
    }
    return array_combine(
      ['provider_id', 'model_id'],
      $data
    );
  }

  /**
   * Loads the configured model provider.
   *
   * @param string|null $source
   *   The optional source string for the model.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy
   *   Returns the model provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadModelProvider(?string $source = NULL): ProviderProxy {
    $data = $this->getModelData($source);
    // Core will handle any error if the plugin ID ($model['provider']) can not
    // be instantiated.
    return $this->aiProvider->createInstance($data['provider_id']);
  }

  /**
   * Get the applicable operation type.
   *
   * @return string
   *   Returns the operation type of the Action.
   */
  abstract protected function getOperationType(): string;

}
