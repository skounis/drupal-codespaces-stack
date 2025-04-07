<?php

namespace Drupal\ai_eca\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai_eca\Service\AiProviderValidatorInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Base class for actions with model-related configuration.
 */
abstract class AiConfigActionBase extends AiActionBase {

  /**
   * The yaml parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * The AI Provider validator.
   *
   * @var \Drupal\ai_eca\Service\AiProviderValidatorInterface
   */
  protected AiProviderValidatorInterface $validator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    $instance->validator = $container->get('ai_eca.provider_validator');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    if (!empty($this->configuration['config'])) {
      try {
        $this->yamlParser->parse($this->configuration['config']);
      }
      catch (ParseException $e) {
        return $return_as_object ? AccessResult::forbidden($e->getMessage()) : FALSE;
      }
    }

    try {
      // Validate YAML formatting.
      $config = $this->yamlParser->parse($this->configuration['config']) ?? [];
    }
    catch (ParseException $e) {
      return $return_as_object ? AccessResult::forbidden($e->getMessage()) : FALSE;
    }

    // Validate config values based on API config.
    $modelData = $this->getModelData();

    /** @var \Drupal\ai\AiProviderInterface $provider */
    $provider = $this->loadModelProvider();
    $violations = $this->validator
      ->addConstraints($this->getExtraConstraints())
      ->validate($provider, $modelData['model_id'], $this->getOperationType(), $config);

    if ($violations->count() > 0) {
      // Prepare error message for safe output.
      $message = implode(' | ', array_map(function (ConstraintViolationInterface $violation) {
        // Eg. [temperature]: This value should be between 0 and 2.
        return $this->t('@key: @message', [
          '@key' => $violation->getPropertyPath(),
          '@message' => (string) $violation->getMessage(),
        ]);
      }, (array) $violations->getIterator()));

      return $return_as_object ? AccessResult::forbidden($message) : FALSE;
    }

    return parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $defaults = parent::defaultConfiguration();
    $defaults['config'] = '';

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Specific configuration for the model'),
      '#default_value' => $this->configuration['config'],
      '#description' => $this->t('Some models require specific configuration settings, like temperature, voice or response_format.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    $config = [];
    try {
      // Validate YAML formatting.
      $config = $this->yamlParser->parse($form_state->getValue('config')) ?? [];
    }
    catch (ParseException $e) {
      $form_state->setErrorByName('config', $e->getMessage());
    }

    // Validate config values based on API config.
    $modelData = $this->getModelData($form_state->getValue('model') ?? 'model_not_specified');

    /** @var \Drupal\ai\AiProviderInterface $provider */
    $provider = $this->loadModelProvider($form_state->getValue('model'));
    $violations = $this->validator
      ->addConstraints($this->getExtraConstraints())
      ->validate($provider, $modelData['model_id'], $this->getOperationType(), $config);

    if ($violations->count() > 0) {
      // Prepare error message for safe output.
      $message = implode(' | ', array_map(function (ConstraintViolationInterface $violation) {
        // Eg. [temperature]: This value should be between 0 and 2.
        return $this->t('@key: @message', [
          '@key' => $violation->getPropertyPath(),
          '@message' => (string) $violation->getMessage(),
        ]);
      }, (array) $violations->getIterator()));

      $form_state->setErrorByName('config', $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['config'] = $form_state->getValue('config');
  }

  /**
   * Get the model config.
   *
   * Tokens have been replaced.
   *
   * @return array
   *   Returns the config for the model.
   */
  protected function getModelConfig(): array {
    return $this->yamlParser->parse($this->configuration['config']) ?? [];
  }

  /**
   * Allow implementing classes to add extra constraints.
   *
   * @return array<string, \Symfony\Component\Validator\Constraint> $constraints
   *   The extra constraints.
   */
  protected function getExtraConstraints(): array {
    return [];
  }

}
