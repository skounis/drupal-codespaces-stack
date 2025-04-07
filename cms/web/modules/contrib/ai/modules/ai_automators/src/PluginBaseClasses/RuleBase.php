<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorTypeNotRunnable;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\Traits\GeneralHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class for all LLM rule helpers.
 */
abstract class RuleBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  use GeneralHelperTrait;
  use StringTranslationTrait;

  /**
   * The LLM type.
   *
   * @var string
   */
  protected string $llmType = 'chat';

  /**
   * The plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiPluginManager;

  /**
   * The form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected AiProviderFormHelper $formHelper;

  /**
   * The prompt JSON decoder.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt JSON decoder.
   */
  public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
  ) {
    $this->aiPluginManager = $pluginManager;
    $this->formHelper = $formHelper;
    $this->promptJsonDecoder = $promptJsonDecoder;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty(array $value, array $automatorConfig = []) {
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return 'Enter a prompt here.';
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'text_long',
      'text',
      'string',
      'string_long',
      'text_with_summary',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    return [
      'context' => 'The cleaned text from the base field.',
      'raw_context' => 'The raw text from the base field. Can include HTML',
      'max_amount' => 'The max amount of entries to set. If unlimited this value will be empty.',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    // Load the AI models.
    $providers = $this->formHelper->getAiProvidersOptions($this->llmType);
    // Add to the start of the array.
    if ($this->llmType == 'chat') {
      $providers = [
        'default_json' => $this->t('Default Advanced JSON model'),
        'default_vision' => $this->t('Default Vision model'),
      ] + $providers;
    }
    else {
      $defaultOperationType = $this->aiPluginManager->getOperationType($this->llmType, TRUE);
      if ($defaultOperationType) {
        $providers = [
          'default' => $this->t('Default %llm_type model', [
            '%llm_type' => $defaultOperationType['label'],
          ]),
        ] + $providers;
      }
    }
    $defaults = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType);
    $provider = $formState->getValue('automator_ai_provider');
    if (!$provider) {
      $provider = $defaultValues['automator_ai_provider'] ?? NULL;
      if (empty($provider)) {
        $provider = key($providers);
      }
      if (empty($provider) && !empty($defaults['provider_id'])) {
        $provider = $defaults['provider_id'];
      }
    }
    $form['automator_ai_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Provider'),
      '#options' => $providers,
      '#default_value' => $provider,
      '#ajax' => [
        'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
        'wrapper' => 'provider_ajax_wrapper',
      ],
    ];
    $form['ajax_prefix'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => 'provider_ajax_wrapper',
      ],
      '#states' => [
        'visible' => [
          ':input[name="automator_ai_provider"]' => ['!value' => ''],
        ],
      ],
    ];

    $llmInstance = NULL;
    $model = NULL;
    if ($provider && $provider !== 'default_json' && $provider !== 'default_vision' && $provider !== 'default') {
      $llmInstance = $this->aiPluginManager->createInstance($provider);
      $model = $formState->getValue('automator_ai_model');
      $models = $llmInstance->getConfiguredModels($this->llmType);
      if (!$model || !in_array($model, array_keys($models))) {
        $model = $defaultValues['automator_ai_model'] ?? NULL;
        if (isset($defaults['model_id']) && !$model) {
          $model = $defaults['model_id'];
        }
        if (empty($model) || !in_array($model, array_keys($models))) {
          $model = key($models);
        }
      }

      $form['ajax_prefix']['automator_ai_model'] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        // Only get chat models.
        '#options' => $models,
        '#default_value' => $model,
        '#ajax' => [
          'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
          'wrapper' => 'provider_ajax_wrapper',
        ],
      ];

      if ($model) {
        $configuration = $llmInstance->getAvailableConfiguration($this->llmType, $model);

        if (count($configuration)) {
          $form['ajax_prefix']['ai_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
          ];
          foreach ($configuration as $key => $definition) {
            $set_key = 'automator_configuration_' . $key;
            $form['ajax_prefix']['ai_settings'][$set_key]['#type'] = $this->formHelper->mapSchemaTypeToFormType($definition);
            $form['ajax_prefix']['ai_settings'][$set_key]['#required'] = $definition['required'] ?? FALSE;
            $form['ajax_prefix']['ai_settings'][$set_key]['#title'] = $definition['label'] ?? $key;
            $form['ajax_prefix']['ai_settings'][$set_key]['#description'] = $definition['description'] ?? '';
            $form['ajax_prefix']['ai_settings'][$set_key]['#default_value'] = $defaultValues[$set_key] ?? $definition['default'] ?? NULL;
            if (isset($definition['constraints'])) {
              foreach ($definition['constraints'] as $form_key => $value) {
                if ($form_key == 'options') {
                  $form['ajax_prefix']['ai_settings'][$set_key]['#options'] = array_combine($value, $value);
                  continue;
                }
                $form['ajax_prefix']['ai_settings'][$set_key]['#' . $form_key] = $value;
              }
            }
          }
        }
      }
    }

    // Add vision if it is available or default vision.
    if (($llmInstance && in_array($model, array_keys($llmInstance->getConfiguredModels('chat', [AiModelCapability::ChatWithImageVision])))) || $provider == 'default_vision') {
      // Add the image field to use.
      $form['ajax_prefix']['automator_configuration_image_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Image Field'),
        '#options' => $this->getGeneralHelper()->getImageMediaFields($entity),
        '#description' => $this->t('Since this is a vision model you can choose to add an image field to the prompt.'),
        '#empty_option' => $this->t('No images'),
        '#default_value' => $defaultValues['automator_configuration_image_field'] ?? NULL,
      ];

      // Also add the possibility to add an image style.
      $form['ajax_prefix']['automator_configuration_image_style'] = [
        '#type' => 'select',
        '#title' => $this->t('Image Style'),
        '#description' => $this->t('Use an optional image style to lower costs and increase speed.'),
        '#empty_option' => $this->t('Use original'),
        '#options' => $this->getGeneralHelper()->getImageStyles(FALSE),
        '#default_value' => $defaultValues['automator_configuration_image_style'] ?? NULL,
        '#states' => [
          'visible' => [
            ':input[name="automator_configuration_image_field"]' => ['!value' => ''],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {

  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $values = $entity->get($automatorConfig['base_field'])->getValue();
    return [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderTokenPrompt($automatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderPrompt($automatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }
    return $prompts;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $entity->set($fieldDefinition->getName(), $values);
  }

  /**
   * Ajax callback to load the models for the selected provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    // Get trigger suffix.
    $trigger = $form_state->getTriggeringElement();
    $suffix = $trigger['#attributes']['data-trigger-suffix'] ?? '';
    return $form['automator_container']['automator_advanced']['ajax_prefix' . $suffix];
  }

  /**
   * Load one extra provider form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $type
   *   The operation type.
   * @param string $suffix
   *   The suffix.
   * @param string $title
   *   The title.
   * @param array $defaultValues
   *   The default values.
   */
  public function extraProviderForm(&$form, FormStateInterface $formState, $type, $suffix, $title, $defaultValues = []) {
    $suffix = '_' . ltrim($suffix, '_');
    // Load the AI models.
    $providers = $this->formHelper->getAiProvidersOptions($type);
    $defaults = $this->aiPluginManager->getDefaultProviderForOperationType($type);
    $provider = $formState->getValue('automator_ai_provider' . $suffix);
    if (!$provider) {
      $provider = $defaultValues['automator_ai_provider' . $suffix] ?? $defaults['provider_id'];
    }

    $form['automator_ai_provider' . $suffix] = [
      '#type' => 'select',
      '#title' => $title,
      '#options' => $providers,
      '#default_value' => $provider,
      '#attributes' => [
        'data-trigger-suffix' => $suffix,
      ],
      '#ajax' => [
        'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
        'wrapper' => 'provider_ajax_wrapper' . $suffix,
      ],
    ];
    $form['ajax_prefix' . $suffix] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => 'provider_ajax_wrapper' . $suffix,
      ],
      '#states' => [
        'visible' => [
          ':input[name="automator_ai_provider' . $suffix . '"]' => ['!value' => ''],
        ],
      ],
    ];

    if ($provider) {
      $llmInstance = $this->aiPluginManager->createInstance($provider);
      $model = $formState->getValue('automator_ai_model' . $suffix);
      if (!$model) {
        $model = $defaultValues['automator_ai_model' . $suffix] ?? $defaults['model_id'];
      }
      if (!$model) {
        $model = key($llmInstance->getConfiguredModels($type));
      }

      $form['ajax_prefix' . $suffix]['automator_ai_model' . $suffix] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        // Only get chat models.
        '#options' => $llmInstance->getConfiguredModels($type),
        '#default_value' => $model,
        '#attributes' => [
          'data-trigger-suffix' => $suffix,
        ],
        '#ajax' => [
          'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
          'wrapper' => 'provider_ajax_wrapper' . $suffix,
        ],
      ];

      if ($model) {
        $configuration = $llmInstance->getAvailableConfiguration($type, $model);

        if (count($configuration)) {
          $form['ajax_prefix' . $suffix]['ai_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
          ];
          foreach ($configuration as $key => $definition) {
            $set_key = 'automator_configuration_' . $key . $suffix;
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#type'] = $this->formHelper->mapSchemaTypeToFormType($definition);
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#required'] = $definition['required'] ?? FALSE;
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#title'] = $definition['label'] ?? $key;
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#description'] = $definition['description'] ?? '';
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#default_value'] = $defaultValues[$set_key] ?? $definition['default'] ?? NULL;
            if (isset($definition['constraints'])) {
              foreach ($definition['constraints'] as $form_key => $value) {
                if ($form_key == 'options') {
                  $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#options'] = array_combine($value, $value);
                  continue;
                }
                $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#' . $form_key] = $value;
              }
            }
          }
        }
      }
    }

    return $form;
  }

  /**
   * Prepare LLM Instance.
   *
   * @param string $operationType
   *   The operation type.
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy
   *   The LLM instance.
   */
  public function prepareLlmInstance($operationType, array &$automatorConfig) {
    $provider = $this->getProvider($automatorConfig);
    $model = $this->getModel($automatorConfig);
    $instance = $this->aiPluginManager->createInstance($provider);

    // Get configuration.
    $config = [];
    $configCast = $instance->getAvailableConfiguration($operationType, $model);
    foreach ($automatorConfig as $key => $val) {
      if (strpos($key, 'configuration_') === 0 && $val) {
        $configKey = str_replace('configuration_', '', $key);
        if (isset($configCast[$configKey]['type'])) {
          $config[$configKey] = CastUtility::typeCast($configCast[$configKey]['type'], $val);
        }
      }
    }
    $instance->setConfiguration($config);
    return $instance;
  }

  /**
   * Run a chat message.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $automatorConfig
   *   The automator configuration.
   * @param \Drupal\ai\Plugin\ProviderProxy $instance
   *   The LLM instance.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The response.
   */
  public function runChatMessage(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL) {
    $text = $this->runRawChatMessage($prompt, $automatorConfig, $instance, $entity);

    // Normalize the response.
    $json = $this->promptJsonDecoder->decode($text);
    if (!is_array($json)) {
      throw new AiAutomatorResponseErrorException('The response was not a valid JSON response. The response was: ' . $text->getText());
    }
    return $this->decodeValueArray($this->promptJsonDecoder->decode($text));
  }

  /**
   * Run a chat message.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $automatorConfig
   *   The automator configuration.
   * @param \Drupal\ai\Plugin\ProviderProxy $instance
   *   The LLM instance.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The response.
   */
  public function runRawChatMessage(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL) {
    $images = [];
    // Check for images or media.
    $possibleImages = [];
    if (!empty($automatorConfig['configuration_image_field'])) {
      $parts[0] = $automatorConfig['configuration_image_field'];
      if (strpos($automatorConfig['configuration_image_field'], '--') !== FALSE) {
        $parts = explode('--', $automatorConfig['configuration_image_field']);
      }
      foreach ($entity->{$parts[0]} as $imageEntityWrapper) {
        $imageEntity = $imageEntityWrapper->entity;
        if (isset($parts[1])) {
          foreach ($imageEntity->{$parts[1]} as $image) {
            $possibleImages[] = $image->entity;
          }
        }
        else {
          $possibleImages[] = $imageEntity;
        }
      }
    }
    foreach ($possibleImages as $possibleImage) {
      // If an image style is set, use it.
      if (!empty($automatorConfig['configuration_image_style'])) {
        $possibleImage = $this->getGeneralHelper()->preprocessImageStyle($possibleImage, $automatorConfig['configuration_image_style']);
      }
      $image = new ImageFile();
      $image->setFileFromFile($possibleImage);
      $images[] = $image;
    }

    // Create new messages.
    $input = new ChatInput([
      new ChatMessage("user", $prompt, $images),
    ]);

    $model = $this->getModel($automatorConfig);
    $response = $instance->chat($input, $model)->getNormalized();

    return $response;
  }

  /**
   * Get the provider.
   *
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return string
   *   The provider.
   */
  protected function getProvider(array $automatorConfig): string {
    if (empty($automatorConfig['ai_provider'])) {
      throw new AiAutomatorTypeNotRunnable('No provider set for the LLM type ' . $this->llmType);
    }
    if ($automatorConfig['ai_provider'] == 'default_json') {
      $automatorConfig['ai_provider'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_complex_json')['provider_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default_vision') {
      $automatorConfig['ai_provider'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_image_vision')['provider_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default') {
      $automatorConfig['ai_provider'] = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType)['provider_id'];
    }
    return $automatorConfig['ai_provider'];
  }

  /**
   * Get the model.
   *
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return string
   *   The model.
   */
  protected function getModel(array &$automatorConfig): string {
    if ($automatorConfig['ai_provider'] == 'default_json') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_complex_json')['model_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default_vision') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_image_vision')['model_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType)['model_id'];
    }
    return $automatorConfig['ai_model'];
  }

  /**
   * Decode a value array.
   *
   * @param mixed $json
   *   The input.
   *
   * @return array
   *   The decoded array.
   */
  public function decodeValueArray($json) {
    // Sometimes it doesn't become a valid JSON response, but many.
    if (isset($json[0]['value'])) {
      $values = [];
      foreach ($json as $val) {
        if (isset($val['value'])) {
          $values[] = $val['value'];
        }
      }
      return $values;
    }
    // Sometimes it sets the wrong key.
    elseif (isset($json[0])) {
      $values = [];
      foreach ($json as $val) {
        if (is_array($val) && isset($val[key($val)])) {
          $values[] = $val[key($val)];
        }
        return $values;
      }
    }
    // Sometimes it does not return with values in GPT 3.5.
    elseif (is_array($json) && isset($json[0][0])) {
      $values = [];
      foreach ($json as $vals) {
        foreach ($vals as $val) {
          if (isset($val)) {
            $values[] = $val;
          }
        }
      }
      return $values;
    }
    elseif (isset($json['value'])) {
      return [$json['value']];
    }
    return [];
  }

}
