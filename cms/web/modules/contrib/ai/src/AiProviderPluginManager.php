<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\ai\OperationType\OperationTypeInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Large Language Model plugin manager.
 */
final class AiProviderPluginManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The message handler.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * Constructs the object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ContainerInterface $container,
    MessengerInterface $messenger,
    UuidInterface $uuid,
  ) {
    parent::__construct('Plugin/AiProvider', $namespaces, $module_handler, AiProviderInterface::class, AiProvider::class);
    $this->alterInfo('ai_provider_info');
    $this->setCacheBackend($cache_backend, 'ai_provider_plugins');
    $this->eventDispatcher = $container->get('event_dispatcher');
    $this->loggerFactory = $container->get('logger.factory');
    $this->cacheBackend = $cache_backend;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $container->get('config.factory');
    $this->messenger = $messenger;
    $this->uuid = $uuid;
  }

  /**
   * Create a provider proxy instance around an AI Provider.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $configuration
   *   The configuration for the plugin.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy
   *   The provider proxy.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   A plugin exception.
   */
  public function createInstance($plugin_id, array $configuration = []): ProviderProxy {
    $plugin = parent::createInstance($plugin_id, $configuration);
    return new ProviderProxy($plugin, $this->eventDispatcher, $this->loggerFactory, $this->uuid, $this->cacheBackend);
  }

  /**
   * Helper function if providers exists and are setup per operation type.
   *
   * @param string $operation_type
   *   The operation type.
   * @param bool $setup
   *   If the provider should be required to be setup.
   *
   * @return bool
   *   If providers exist.
   */
  public function hasProvidersForOperationType(string $operation_type, bool $setup = TRUE): bool {
    $providers = $this->getProvidersForOperationType($operation_type, $setup);
    return !empty($providers);
  }

  /**
   * Gets the default possible provider name and model for an operation type.
   *
   * @param string $operation_type
   *   The operation type.
   *
   * @return array|null
   *   The default provider name and model or null.
   */
  public function getDefaultProviderForOperationType(string $operation_type): ?array {
    $config = $this->configFactory->get('ai.settings');
    return $config->get('default_providers.' . $operation_type, NULL);
  }

  /**
   * Gets all providers for an operation type.
   *
   * @param string $operation_type
   *   The operation type.
   * @param bool $setup
   *   If the provider should be required to be setup.
   * @param array $capabilities
   *   The capabilities the provider should have.
   *
   * @return array
   *   The providers.
   */
  public function getProvidersForOperationType(string $operation_type, bool $setup = TRUE, array $capabilities = []): array {
    $providers = [];
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      $provider_entity = $this->createInstance($id);
      if (in_array($operation_type, $provider_entity->getSupportedOperationTypes())) {
        if (!$setup || $provider_entity->isUsable($operation_type, $capabilities)) {
          $providers[$id] = $definition;
        }
      }
    }
    return $providers;
  }

  /**
   * Get simple default provider options for an operation type.
   *
   * @param string $operation_type
   *   The operation type.
   *
   * @return string
   *   The simple default provider option.
   */
  public function getSimpleDefaultProviderOptions(string $operation_type): string {
    $default_provider = $this->getDefaultProviderForOperationType($operation_type);
    return !empty($default_provider['provider_id']) && !empty($default_provider['model_id']) ?
      $default_provider['provider_id'] . '__' . $default_provider['model_id'] : '';
  }

  /**
   * Gets a simple options list of providers and models for an operation type.
   *
   * @param string $operation_type
   *   The operation type.
   * @param bool $empty
   *   If empty choices should be included.
   * @param bool $setup
   *   If the provider should be required to be setup.
   * @param array $capabilities
   *   The capabilities the provider should have.
   *
   * @return array
   *   Key that is __ separated for provider and model.
   */
  public function getSimpleProviderModelOptions(string $operation_type, bool $empty = TRUE, bool $setup = TRUE, array $capabilities = []): array {
    $providers = $this->getProvidersForOperationType($operation_type, $setup, $capabilities);
    $options = [];
    if ($empty) {
      $options[''] = $this->t('- None -');
    }
    foreach ($providers as $id => $definition) {
      $provider = $this->createInstance($id);
      try {
        $models = $provider->getConfiguredModels($operation_type, $capabilities);
        foreach ($models as $model_id => $model_name) {
          $options[$id . '__' . $model_id] = $definition['label'] . ' - ' . $model_name;
        }
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('ai')->error('Error getting models for provider %provider: %error', [
          '%provider' => $id,
          '%error' => $e->getMessage(),
        ]);
      }
    }
    return $options;
  }

  /**
   * Loads the actual provider from a simple option.
   *
   * @param string $option
   *   The option from simple options list.
   *
   * @return \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy|null
   *   The provider or NULL.
   */
  public function loadProviderFromSimpleOption(string $option): AiProviderInterface|ProviderProxy|NULL {
    $parts = explode('__', $option);
    if (count($parts) === 2) {
      $provider = $this->createInstance($parts[0]);
      if ($provider->isUsable()) {
        return $provider;
      }
    }
    return NULL;
  }

  /**
   * Get model name from simple option.
   *
   * @param string $option
   *   The option from simple options list.
   *
   * @return string
   *   The model name.
   */
  public function getModelNameFromSimpleOption(string $option): string {
    $parts = explode('__', $option);
    if (count($parts) === 2) {
      return $parts[1];
    }
    return '';
  }

  /**
   * Get operation types.
   *
   * @return array
   *   The list of operation types.
   */
  public function getOperationTypes(): array {
    // Load from cache.
    $data = $this->cacheBackend->get('ai_operation_types');
    if (!empty($data->data)) {
      return $data->data;
    }
    // Look in the OperationType/** directories.
    $operation_types = [];
    $base_path = $this->moduleHandler->getModule('ai')->getPath() . '/src/OperationType';
    $directories = new \RecursiveDirectoryIterator($base_path);
    $iterator = new \RecursiveIteratorIterator($directories);
    $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);
    foreach ($regex as $file) {
      $interface = $this->getInterfaceFromFile($file[0]);
      if ($interface && $this->doesInterfaceExtend($interface, OperationTypeInterface::class)) {
        $reflection = new \ReflectionClass($interface);
        $attributes = $reflection->getAttributes(OperationType::class);
        foreach ($attributes as $attribute) {
          $operation_types[] = [
            'id' => $attribute->newInstance()->id,
            'label' => $attribute->newInstance()->label->render(),
          ];
        }
      }
    }

    // Save to cache.
    $this->cacheBackend->set('ai_operation_types', $operation_types);

    return $operation_types;
  }

  /**
   * Get operation type.
   *
   * @param string $operation_type
   *   The operation type.
   * @param bool $check_has_default
   *   Check if the operation type has a default value.
   *
   * @return array|null
   *   The id and label or nothing.
   */
  public function getOperationType(string $operation_type, bool $check_has_default = FALSE): array|null {
    $operation_types = $this->getOperationTypes();
    foreach ($operation_types as $operation) {
      if ($operation['id'] === $operation_type) {
        if (!$check_has_default || $this->operationTypeHasDefault($operation_type)) {
          return $operation;
        }
      }
    }
    // Didn't find it.
    return NULL;
  }

  /**
   * Operation type has default.
   *
   * @param string $operation_type
   *   The operation type.
   *
   * @return bool
   *   If the operation type has a default.
   */
  public function operationTypeHasDefault(string $operation_type): bool {
    $config = $this->configFactory->get('ai.settings');
    return !empty($config->get('default_providers.' . $operation_type));
  }

  /**
   * A helper setting for provider to allow them to be default on setup.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string $provider_id
   *   The provider ID.
   * @param string $model_id
   *   The model ID.
   *
   * @return bool
   *   If the default was set.
   */
  public function defaultIfNone(string $operation_type, string $provider_id, string $model_id): bool {
    $config = $this->configFactory->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    // If its set, we just return false.
    if (!empty($default_providers[$operation_type])) {
      return FALSE;
    }
    $default_providers[$operation_type] = [
      'provider_id' => $provider_id,
      'model_id' => $model_id,
    ];
    // Set a message to the user.
    $this->loggerFactory->get('ai')->notice("Default provider $provider_id with model $model_id set for operation type $operation_type.");
    $config->set('default_providers', $default_providers)->save();
    return TRUE;
  }

  /**
   * Extracts the fully qualified interface name from a file.
   *
   * @param string $file
   *   The file path.
   *
   * @return string|null
   *   The fully qualified interface name, or NULL if not found.
   */
  protected function getInterfaceFromFile($file) {
    $contents = file_get_contents($file);

    // Match namespace and interface declarations.
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $matches)) {
      $namespace = $matches[1];
    }

    // Match on starts with interface and has extends in it.
    if (preg_match('/interface\s+([^ ]+)\s+extends\s+([^ ]+)/i', $contents, $matches) && isset($namespace)) {
      $interface = $matches[1];
      return $namespace . '\\' . $interface;
    }

    return NULL;
  }

  /**
   * Checks if an interface extends another interface.
   *
   * @param string $interface
   *   The interface name.
   * @param string $baseInterface
   *   The base interface name.
   *
   * @return bool
   *   TRUE if the interface extends the base interface, FALSE otherwise.
   */
  protected function doesInterfaceExtend($interface, $baseInterface) {
    try {
      $reflection = new \ReflectionClass($interface);

      if ($reflection->isInterface() && in_array($baseInterface, $reflection->getInterfaceNames())) {
        return TRUE;
      }
    }
    catch (\ReflectionException $e) {
      // Ignore.
    }

    return FALSE;
  }

  /**
   * Gives notice that a provider is disabled.
   *
   * @param string $provider_id
   *   The provider ID.
   */
  public function providerDisabled(string $provider_id) {
    // Get AI settings as editable.
    $defaults = $this->configFactory->getEditable('ai.settings')->get('default_providers');
    $changed = FALSE;
    foreach ($defaults as $key => $value) {
      if ($value['provider_id'] == $provider_id) {
        // Remove the provider from the default providers.
        unset($defaults[$key]);
        // Set that we need to change the config.
        $changed = TRUE;
      }
    }
    if ($changed) {
      // Save the updated default providers, if needed.
      $this->configFactory->getEditable('ai.settings')->set('default_providers', $defaults)->save();
    }
    // Notify other modules that a provider was disabled.
    $this->eventDispatcher->dispatch(new ProviderDisabledEvent($provider_id), ProviderDisabledEvent::EVENT_NAME);
  }

}
