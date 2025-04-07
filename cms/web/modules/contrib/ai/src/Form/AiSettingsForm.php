<?php

namespace Drupal\ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI module.
 */
class AiSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai.settings';

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The hard coded selections to add for filtering purposes.
   *
   * @var array
   */
  protected $hardcodedSelections = [
    [
      'id' => 'chat_with_image_vision',
      'actual_type' => 'chat',
      'label' => 'Chat with Image Vision',
      'filter' => [AiModelCapability::ChatWithImageVision],
    ],
    [
      'id' => 'chat_with_complex_json',
      'actual_type' => 'chat',
      'label' => 'Chat with Complex JSON',
      'filter' => [AiModelCapability::ChatJsonOutput],
    ],
  ];

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $config = $this->config(static::CONFIG_NAME);

    $form['default_providers'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Providers'),
      '#open' => TRUE,
      '#weight' => 10,
      '#description' => $this->t('These are default providers for each operation type that external modules can use or show on their configurations pages. Choose a provider from the <a href="@ai">AI module homepage</a>, add it to your project, then install and <a href="@configure">configure</a> it first.', [
        '@ai' => 'https://www.drupal.org/project/ai',
        '@configure' => Url::fromRoute('ai.admin_providers')->toString(),
      ]),
    ];

    $operation_types = $this->providerManager->getOperationTypes();
    $default_providers = $config->get('default_providers') ?? [];
    // Get all providers.
    /** @var \Drupal\ai\AiProviderInterface[] $providers */
    $providers = [];
    foreach ($this->providerManager->getDefinitions() as $id => $definition) {
      $providers[$id] = $this->providerManager->createInstance($id);
    }
    if (count($providers) === 0) {
      $this->messenger()->addWarning($this->t('Choose at least one AI provider module from those listed on the AI module homepage, add to your project, install and configure it. Then update the AI Settings on this page.'));
    }

    // Add the hardcoded selections of filtered types.
    $operation_types = array_merge($operation_types, $this->hardcodedSelections);

    foreach ($operation_types as $operation_type) {
      // Get all providers that allows for a specific operation type.
      $options = [
        '' => 'No default',
      ];
      $filters = $operation_type['filter'] ?? [];
      foreach ($providers as $provider) {
        if ($provider->isUsable($operation_type['actual_type'] ?? $operation_type['id'], $filters)) {
          $options[$provider->getPluginId()] = $provider->getPluginDefinition()['label'];
        }
      }
      $default_provider = $form_state->getValue('operation__' . $operation_type['id']);
      if (!$default_provider) {
        $default_provider = $default_providers[$operation_type['id']]['provider_id'] ?? '';
      }
      $form['default_providers'][$operation_type['id']] = [
        '#type' => 'fieldset',
        '#title' => $operation_type['label'],
      ];
      $form['default_providers'][$operation_type['id']]['operation__' . $operation_type['id']] = [
        '#type' => 'select',
        '#title' => $this->t('Default Provider'),
        '#options' => $options,
        '#default_value' => !empty($providers[$default_provider]) ? $default_provider : '',
        '#ajax' => [
          'callback' => '::loadModels',
          'wrapper' => 'model__' . $operation_type['id'],
        ],
      ];

      $form['default_providers'][$operation_type['id']]['model'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'model__' . $operation_type['id'],
        ],
      ];

      // Add the model id field if the provider is set.
      if ($default_provider && !empty($providers[$default_provider])) {
        $models = [];
        try {
          if ($providers[$default_provider]->isUsable($operation_type['actual_type'] ?? $operation_type['id'])) {
            $models = $providers[$default_provider]->getConfiguredModels($operation_type['actual_type'] ?? $operation_type['id'], $filters);
          }
          else {
            $this->messenger()->addWarning($this->t('The default %operation provider (%provider_id) is not currently usable. Please review your configuration.', [
              '%operation' => $operation_type['label'],
              '%provider_id' => $default_provider,
            ]));
          }
        }
        catch (\Exception $e) {
          // Don't crash if the provider is not fully configured.
          $this->messenger()->addError($e->getMessage());
          // In case the exception is related to authentication.
          if ($e->getCode() == 401 || method_exists($e, 'getStatusCode') && $e->getStatusCode() == 401) {
            $api_key = $providers[$default_provider]->getConfig()->get('api_key');
            if (!empty($api_key)) {
              $this->messenger()->addError($this->t('You can update or add the API Key <a href="@url" target="_blank">here</a>', ['@url' => Url::fromRoute('entity.key.edit_form', ['key' => $api_key])->toString()]));
            }
          }
        }
        $form['default_providers'][$operation_type['id']]['model']['model__' . $operation_type['id']] = [
          '#type' => 'select',
          '#title' => $this->t('Default Model'),
          '#default_value' => $default_providers[$operation_type['id']]['model_id'] ?? '',
          '#options' => $models,
          '#empty_option' => $this->t('- Select -'),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();
    foreach ($this->providerManager->getOperationTypes() as $operation_type) {

      // We only want to ensure a model is selected for each operation that
      // has a default.
      if (empty($values['operation__' . $operation_type['id']])) {
        continue;
      }

      if (!isset($values['model__' . $operation_type['id']])) {

        // In this scenario, the user has not yet been given the chance to
        // select a model. This is typically because JavaScript is disabled.
        $form_state->setRebuild();
      }
      elseif (empty($values['model__' . $operation_type['id']])) {

        // The user has the option to select a model but has not, show a
        // validation error.
        $message = $this->t('You have selected a provider for @operation but have not selected a model.', [
          '@operation' => $operation_type['label'],
        ]);
        $form_state->setErrorByName('model__' . $operation_type['id'], $message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set the default providers array.
    $default_providers = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'operation__') === 0) {
        $operation_type = substr($key, 11);
        if (empty($value)) {
          continue;
        }
        $default_providers[$operation_type] = [
          'provider_id' => $value,
          'model_id' => $form_state->getValue('model__' . $operation_type),
        ];
      }
    }

    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('default_providers', $default_providers)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to load models.
   */
  public function loadModels(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $operation_type = substr($trigger['#name'], 11);
    return $form['default_providers'][$operation_type]['model']['model__' . $operation_type];
  }

}
