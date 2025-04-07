<?php

namespace Drupal\eca\PluginManager;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eca\Plugin\Action\ActionInterface;

/**
 * Decorates the action manager to make ECA actions only available in ECA.
 *
 * Additionally uses list cache tags of the action config entity by default,
 * because action config entities are used as pre-configured actions.
 */
class Action extends ActionManager {

  /**
   * The action manager that is being decorated by this class.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $decoratedManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\PluginManager\Action
   *   The service instance.
   */
  public static function get(): Action {
    return \Drupal::service('plugin.manager.eca.action');
  }

  /**
   * Get the action manager that is being decorated by this class.
   *
   * @return \Drupal\Core\Action\ActionManager
   *   The manager being decorated.
   */
  public function getDecoratedActionManager(): ActionManager {
    return $this->decoratedManager;
  }

  /**
   * Set the action manager that is being decorated by this class.
   *
   * @param \Drupal\Core\Action\ActionManager $manager
   *   The manager being decorated.
   */
  public function setDecoratedActionManager(ActionManager $manager): void {
    $this->decoratedManager = $manager;
  }

  /**
   * Set the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): void {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      // @codingStandardsIgnoreLine @phpstan-ignore-next-line
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleHandler() {
    return $this->decoratedManager->getModuleHandler();
  }

  /**
   * {@inheritdoc}
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend, $cache_key, array $cache_tags = []): void {
    if (empty($cache_tags)) {
      // By default, use the cache tags of the action entity type. This makes
      // sure, that newly added pre-configured actions are available.
      $cache_tags = $this->getEntityTypeManager()->getDefinition('action')->getListCacheTags();
    }
    if (isset($this->decoratedManager)) {
      $this->decoratedManager->setCacheBackend($cache_backend, $cache_key, $cache_tags);
    }
    parent::setCacheBackend($cache_backend, $cache_key, $cache_tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $this->definitions = $this->filterEcaDefinitions($this->decoratedManager->getDefinitions());
    return $this->definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    return $this->decoratedManager->getDefinition($plugin_id, $exception_on_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return $this->decoratedManager->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    $this->decoratedManager->clearCachedDefinitions();
    parent::clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE): void {
    $this->decoratedManager->useCaches($use_caches);
    parent::useCaches($use_caches);
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    $this->decoratedManager->processDefinition($definition, $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->decoratedManager->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->decoratedManager->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->decoratedManager->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return $this->decoratedManager->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->decoratedManager->getInstance($options);
  }

  /**
   * Removes ECA action definition.
   *
   * @param array $definitions
   *   The definitions to filter.
   */
  protected function filterEcaDefinitions(array $definitions): array {
    return array_filter($definitions, static function ($definition) {
      if ($class = ($definition['class'] ?? NULL)) {
        if (is_a($class, ActionInterface::class, TRUE)) {
          return $class::externallyAvailable();
        }
      }
      return TRUE;
    });
  }

}
