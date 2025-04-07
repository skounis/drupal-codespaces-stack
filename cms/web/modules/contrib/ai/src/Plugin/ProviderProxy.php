<?php

namespace Drupal\ai\Plugin;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiMissingFeatureException;
use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\OperationTypeInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Proxies calls to a plugin and logs before and after the method execution.
 */
class ProviderProxy {

  /**
   * The plugin to proxy.
   *
   * @var object
   */
  protected $plugin;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * PluginLoggingProxy constructor.
   *
   * @param \Drupal\ai\Base\AiProviderClientBase $plugin
   *   The plugin to proxy.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(AiProviderClientBase $plugin, EventDispatcherInterface $event_dispatcher, LoggerChannelFactoryInterface $logger_factory, UuidInterface $uuid, CacheBackendInterface $cache_backend) {
    $this->plugin = $plugin;
    $this->eventDispatcher = $event_dispatcher;
    $this->loggerFactory = $logger_factory;
    $this->uuid = $uuid;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * Proxy for calling methods using magic methods.
   *
   * @param string $name
   *   The method name.
   * @param array $arguments
   *   The arguments to pass to the method.
   *
   * @return mixed
   *   The result of the method call.
   */
  public function __call($name, $arguments) {
    $reflection = new \ReflectionClass($this->plugin);

    if ($reflection->hasMethod($name)) {
      $method = $reflection->getMethod($name);

      if ($method->isPublic()) {
        return $this->wrapperCall($method, $arguments);
      }
    }

    if (!method_exists($this->plugin, $name)) {
      throw new AiOperationTypeMissingException("Method {$name} does not exist on provider " . $this->plugin->getPluginId());
    }
  }

  /**
   * All wrapper methods around each call.
   *
   * @param \ReflectionMethod $method
   *   The method to call.
   * @param array $arguments
   *   The arguments to pass to the method.
   *
   * @return mixed
   *   The result of the method call.
   */
  protected function wrapperCall(\ReflectionMethod $method, $arguments) {
    // Get the operation type trigger methods.
    $proxiedMethods = $this->getOperationTypeTriggerMethods(get_class($this->plugin));
    // Set the operation type from the method name.
    $operation_type = $this->camelToSnake($method->getName());
    // If the method is not a trigger method, just call it.
    if (!in_array($method->getName(), $proxiedMethods)) {
      // Special cases.
      switch ($method->getName()) {
        // Special add on for the configured models.
        case 'getConfiguredModels':
          return $this->resetConfiguredModels($method->invokeArgs($this->plugin, $arguments), $arguments);

        // Make sure to cache the api definition.
        case 'getApiDefinition':
          return $this->cacheApiDefinition($arguments);
      }
      return $method->invokeArgs($this->plugin, $arguments);
    }

    // If input is not set, input is model ID.
    if (!isset($arguments[1])) {
      $arguments[1] = $arguments[0];
      $arguments[0] = NULL;
    }

    // Tags might not always be set.
    if (!isset($arguments[2])) {
      $arguments[2] = [];
    }

    // The model is missing completely.
    if (!is_string($arguments[1])) {
      throw new AiBadRequestException('Model ID is missing in your request.');
    }

    // Normalize the configuration.
    $this->plugin->configuration = $this->plugin->normalizeConfiguration($operation_type, $arguments[1]);

    // Set some default tags.
    $this->plugin->resetTags();
    $this->plugin->setTag($operation_type);
    foreach ($arguments[2] as $tag) {
      $this->plugin->setTag($tag);
    }

    // Create a unique event id.
    $event_id = $this->uuid->generate();

    // Invoke the pre generate response event.
    $pre_generate_event = new PreGenerateResponseEvent($event_id, $this->plugin->getPluginId(), $operation_type, $this->plugin->configuration, $arguments[0], $arguments[1], $this->plugin->getTags(), $this->plugin->getDebugData());

    $this->eventDispatcher->dispatch($pre_generate_event, PreGenerateResponseEvent::EVENT_NAME);
    // Get the possible new auth, configuration and input from the event.
    $this->plugin->configuration = $pre_generate_event->getConfiguration();
    $arguments[0] = $pre_generate_event->getInput();
    // Only set the authentication if it is set.
    if ($pre_generate_event->getAuthentication()) {
      $this->plugin->setAuthentication($pre_generate_event->getAuthentication());
    }

    // Handle any changes to the tags made in the event.
    $this->plugin->resetTags();

    foreach ($pre_generate_event->getTags() as $tag) {
      $this->plugin->setTag($tag);
    }

    // Trigger the provider and try to catch where it went wrong.
    try {
      $response = $method->invokeArgs($this->plugin, $arguments);
    }
    // Response is wrong.
    catch (ClientExceptionInterface $e) {
      $this->loggerFactory->get('ai')->error('Error invoking client: @error', ['@error' => $e->getMessage()]);
      throw new AiBadRequestException('Error invoking client: ' . $e->getMessage());
    }
    // If the provider does a responder error.
    catch (AiResponseErrorException $e) {
      $this->loggerFactory->get('ai')->error('Error invoking model response: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
    // If its a missing feature exception.
    catch (AiMissingFeatureException $e) {
      $this->loggerFactory->get('ai')->error('The provider was missing a requested feature: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
    // If its a quota exception.
    catch (AiQuotaException $e) {
      $this->loggerFactory->get('ai')->error('The provider claims missing quota: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
    // If its a rate limit exception.
    catch (AiRateLimitException $e) {
      $this->loggerFactory->get('ai')->error('The provider claims rate limit: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
    // Its not safe.
    catch (AiUnsafePromptException $e) {
      $this->loggerFactory->get('ai')->error('The Prompt is unsafe: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
    // If an request error happens.
    catch (AiRequestErrorException $e) {
      $this->loggerFactory->get('ai')->error('Error invoking model response: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
    // Anything else is probably due to a bad request.
    catch (\Exception $e) {
      $this->loggerFactory->get('ai')->error('Error invoking model response: @error', ['@error' => $e->getMessage()]);
      throw new AiRequestErrorException('Error invoking model response: ' . $e->getMessage());
    }

    // Invoke the post generate response event.
    $post_generate_event = new PostGenerateResponseEvent($event_id, $this->plugin->getPluginId(), $operation_type, $this->plugin->configuration, $arguments[0], $arguments[1], $response, $this->plugin->getTags(), $this->plugin->getDebugData(), $pre_generate_event->getAllMetadata());
    $this->eventDispatcher->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);
    // Get a potential new response from the event.
    $response = $post_generate_event->getOutput();

    // Since we need to attach events on streaming responses as well.
    if ($response->getNormalized() instanceof \IteratorAggregate) {
      $response->getNormalized()->setRequestThreadId($event_id);
    }

    // Return the response.
    return $response;
  }

  /**
   * Proxy for getting properties.
   *
   * @param string $name
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  public function __get($name) {
    return $this->plugin->$name;
  }

  /**
   * Proxy for setting properties.
   *
   * @param string $name
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  public function __set($name, $value) {
    $this->plugin->$name = $value;
  }

  /**
   * Cache the API definition.
   *
   * @param array $arguments
   *   The arguments.
   *
   * @return array
   *   The API definition.
   */
  public function cacheApiDefinition(array $arguments): array {
    $cache_id = 'ai:api_definition:' . $this->plugin->getPluginId();
    if ($cache = $this->cacheBackend->get($cache_id)) {
      return $cache->data;
    }
    $defintion = $this->plugin->getApiDefinition($arguments);
    $this->cacheBackend->set($cache_id, $defintion, CacheBackendInterface::CACHE_PERMANENT);
    return $defintion;
  }

  /**
   * Reset the configured models.
   *
   * @param array $models
   *   The models.
   * @param array $arguments
   *   The operation type and the capabilities.
   *
   * @return array
   *   The models.
   */
  public function resetConfiguredModels(array $models, array $arguments): array {
    // Load all the extra models.
    $config = $this->plugin->getModelsConfig();
    $plugin_id = $this->plugin->getPluginId();
    if (empty($arguments[0]) || empty($config) || empty($config[$plugin_id]) || empty($config[$plugin_id][$arguments[0]])) {
      return $models;
    }
    $provider_config = $config[$plugin_id][$arguments[0]];
    foreach ($provider_config as $model_id => $model) {
      // Override.
      if (isset($models[$model_id]) && !empty($model['label'])) {
        $models[$model_id] = $model['label'];
      }
      // Add.
      elseif (!empty($model['label'])) {
        $models[$model_id] = $model['label'];
      }
    }
    return $models;
  }

  /**
   * Gets the providers module name.
   *
   * @return string
   *   The module name.
   */
  public function getModuleDataName(): string {
    if (preg_match('/^Drupal\\\\([a-zA-Z_]+)\\\\/', $this->plugin::class, $matches)) {
      return $matches[1];
    }
    return '';
  }

  /**
   * We have to figure out the operation type trigger methods.
   *
   * @param string $className
   *   The class name.
   *
   * @return array
   *   List of operation type trigger methods.
   */
  public function getOperationTypeTriggerMethods(string $className): array {
    $reflectionClass = new \ReflectionClass($className);
    $methods = $reflectionClass->getMethods();

    $interfaces = $reflectionClass->getInterfaces();

    $methodInterfaces = [];

    foreach ($methods as $method) {
      $methodName = $method->getName();

      foreach ($interfaces as $interface) {
        if ($interface->hasMethod($methodName)) {
          // Get the parent interface.
          foreach ($interface->getInterfaces() as $parentInterface) {
            // Only run if its the actual trigger method name of the interface.
            if (isset($parentInterface->name) && OperationTypeInterface::class === $parentInterface->name &&
              str_replace('interface', '', strtolower($interface->getShortName())) == strtolower($methodName)) {
              $methodInterfaces[] = $methodName;
            }
          }
        }
      }
    }

    return $methodInterfaces;
  }

  /**
   * Convert camel case to snake case.
   *
   * @param string $camelCase
   *   The camel case string.
   *
   * @return string
   *   The snake case string.
   */
  protected function camelToSnake($camelCase) {
    $pattern = '/(?<=\\w)(?=[A-Z])|(?<=[a-z])(?=[0-9])/';
    $snakeCase = preg_replace($pattern, '_', $camelCase);
    return strtolower($snakeCase);
  }

}
