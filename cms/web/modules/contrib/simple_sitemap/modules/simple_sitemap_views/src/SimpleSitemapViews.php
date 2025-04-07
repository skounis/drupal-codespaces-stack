<?php

namespace Drupal\simple_sitemap_views;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Entity\SimpleSitemapType;
use Drupal\simple_sitemap_views\Plugin\views\display_extender\SimpleSitemapDisplayExtender;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Class to manage sitemap data for views.
 *
 * @todo Replace with something similar to CustomLinkManager including
 * getting/setting of sitemaps.
 */
class SimpleSitemapViews {

  /**
   * Separator between arguments.
   */
  public const ARGUMENT_SEPARATOR = '/';

  /**
   * Views display extender plugin ID.
   */
  protected const PLUGIN_ID = 'simple_sitemap_display_extender';

  /**
   * View entities storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $viewStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * SimpleSitemapViews constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The current active database's master connection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    QueueFactory $queue_factory,
    Connection $database,
  ) {
    $this->viewStorage = $entity_type_manager->getStorage('view');
    $this->configFactory = $config_factory;
    $this->queueFactory = $queue_factory;
    $this->database = $database;
  }

  /**
   * Checks that views support is enabled.
   *
   * @return bool
   *   Returns TRUE if support is enabled, and FALSE otherwise.
   */
  public function isEnabled(): bool {
    // Support enabled when views display extender is enabled.
    $enabled = Views::getEnabledDisplayExtenders();
    return isset($enabled[self::PLUGIN_ID]);
  }

  /**
   * Enables sitemap support for views.
   */
  public function enable(): void {
    $config = $this->configFactory->getEditable('views.settings');
    $display_extenders = $config->get('display_extenders') ?: [];

    // Enable views display extender plugin.
    $display_extenders[self::PLUGIN_ID] = self::PLUGIN_ID;
    $config->set('display_extenders', $display_extenders);
    $config->save();
  }

  /**
   * Disables sitemap support for views.
   */
  public function disable(): void {
    $config = $this->configFactory->getEditable('views.settings');
    $display_extenders = $config->get('display_extenders') ?: [];

    // Disable views display extender plugin.
    unset($display_extenders[self::PLUGIN_ID]);
    $config->set('display_extenders', $display_extenders);
    $config->save();

    // Clear the table with indexed arguments.
    // Clear the garbage collection queue.
    $this->removeArgumentsFromIndex();
    $queue = $this->queueFactory->get('simple_sitemap.views.garbage_collector');
    $queue->deleteQueue();

    // Remove the views URL generator from all sitemap types.
    $types = SimpleSitemapType::loadMultiple();
    foreach ($types as $type) {
      if ($type->hasUrlGenerator('views')) {
        $type->set('url_generators', array_keys(array_diff_key($type->getUrlGenerators(), ['views' => ''])))->save();
      }
    }
  }

  /**
   * Gets the display extender.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A view executable instance.
   * @param string|null $display_id
   *   The display id. If empty uses the current display.
   *
   * @return \Drupal\simple_sitemap_views\Plugin\views\display_extender\SimpleSitemapDisplayExtender|null
   *   The display extender.
   */
  public function getDisplayExtender(ViewExecutable $view, ?string $display_id = NULL): ?SimpleSitemapDisplayExtender {
    // Ensure the display was correctly set.
    if (!$view->setDisplay($display_id)) {
      return NULL;
    }

    $extenders = $view->display_handler->getExtenders();
    $extender = $extenders[self::PLUGIN_ID] ?? NULL;

    if ($extender instanceof SimpleSitemapDisplayExtender) {
      return $extender;
    }

    return NULL;
  }

  /**
   * Gets the sitemap settings for view display.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A view executable instance.
   * @param string $variant
   *   The ID of the sitemap.
   * @param string|null $display_id
   *   The display id. If empty uses the current display.
   *
   * @return array|null
   *   The sitemap settings if the display is indexed, NULL otherwise.
   */
  public function getSitemapSettings(ViewExecutable $view, string $variant, ?string $display_id = NULL): ?array {
    $extender = $this->getDisplayExtender($view, $display_id);

    // Retrieve the sitemap settings from the extender.
    if ($extender && $extender->hasSitemapSettings()) {
      $settings = $extender->getSitemapSettings($variant);

      if ($settings['index']) {
        return $settings;
      }
    }

    return NULL;
  }

  /**
   * Gets the indexable arguments for view display.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A view executable instance.
   * @param string $variant
   *   The ID of the sitemap.
   * @param string|null $display_id
   *   The display id. If empty uses the current display.
   *
   * @return array
   *   Indexable arguments identifiers.
   */
  public function getIndexableArguments(ViewExecutable $view, string $variant, ?string $display_id = NULL): array {
    $settings = $this->getSitemapSettings($view, $variant, $display_id);
    $indexable_arguments = [];

    // Find indexable arguments.
    if ($settings) {
      $arguments = array_keys($view->display_handler->getHandlers('argument'));
      $bits = explode('/', $view->getPath());
      $arg_index = 0;

      // Required arguments.
      foreach ($bits as $bit) {
        if ($bit == '%' || str_starts_with($bit, '%')) {
          $indexable_arguments[] = $arguments[$arg_index] ?? $bit;
          $arg_index++;
        }
      }

      if (!empty($settings['arguments'])) {
        if ($arg_index > 0) {
          $arguments = array_slice($arguments, $arg_index);
        }

        // Optional arguments.
        foreach ($arguments as $argument_id) {
          if (empty($settings['arguments'][$argument_id])) {
            break;
          }
          $indexable_arguments[] = $argument_id;
        }
      }
    }

    return $indexable_arguments;
  }

  /**
   * Adds view arguments to the index.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A view executable instance.
   * @param array $args
   *   Array of arguments to add to the index.
   * @param string|null $display_id
   *   The display id. If empty uses the current display.
   *
   * @return bool
   *   TRUE if the arguments are added to the index, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function addArgumentsToIndex(ViewExecutable $view, array $args, ?string $display_id = NULL): bool {
    foreach ($this->getSitemaps() as $sitemap) {
      if ($this->addArgumentsToIndexByVariant($view, $sitemap->id(), $args, $display_id)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Adds view arguments to the index by the sitemap variant.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A view executable instance.
   * @param string $variant
   *   The ID of the sitemap.
   * @param array $args
   *   Array of arguments to add to the index.
   * @param string|null $display_id
   *   The display id. If empty uses the current display.
   *
   * @return bool
   *   TRUE if the arguments are added to the index, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function addArgumentsToIndexByVariant(ViewExecutable $view, string $variant, array $args, ?string $display_id = NULL): bool {
    // An array of arguments to be added to the index can not be empty.
    // Also ensure the display was correctly set.
    if (empty($args) || !$view->setDisplay($display_id)) {
      return FALSE;
    }

    // Check that indexing of at least one argument is enabled.
    $indexable_arguments = $this->getIndexableArguments($view, $variant);
    if (empty($indexable_arguments)) {
      return FALSE;
    }

    // Check that the number of identifiers is equal to the number of values.
    $args_ids = array_slice($indexable_arguments, 0, count($args));
    if (count($args_ids) !== count($args)) {
      return FALSE;
    }

    // Check that the current number of rows in the index does not
    // exceed the specified number.
    $condition = Database::getConnection()->condition('AND');
    $condition->condition('view_id', $view->id());
    $condition->condition('display_id', $view->current_display);
    $settings = $this->getSitemapSettings($view, $variant);
    $max_links = is_numeric($settings['max_links']) ? $settings['max_links'] : 0;
    if ($max_links > 0 && $this->getArgumentsFromIndexCount($condition) >= $max_links) {
      return FALSE;
    }

    // Convert the set of identifiers and a set of values to string.
    $args_ids = $this->convertArgumentsArrayToString($args_ids);
    $args_values = $this->convertArgumentsArrayToString($args);
    $condition->condition('arguments_ids', $args_ids);
    $condition->condition('arguments_values', $args_values);
    // Check that this set of arguments has not yet been indexed.
    if ($this->getArgumentsFromIndexCount($condition)) {
      return FALSE;
    }

    // Check that the view result is not empty for this set of arguments.
    $params = array_merge([$view->id(), $view->current_display], $args);
    $view_result = call_user_func_array('views_get_view_result', $params);
    if (empty($view_result)) {
      return FALSE;
    }

    // Add a set of arguments to the index.
    $query = $this->database->insert('simple_sitemap_views');
    $query->fields([
      'view_id' => $view->id(),
      'display_id' => $view->current_display,
      'arguments_ids' => $args_ids,
      'arguments_values' => $args_values,
    ]);

    return (bool) $query->execute();
  }

  /**
   * Get arguments from index.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface|null $condition
   *   The query conditions.
   * @param int|null $limit
   *   The number of records to return from the result set. If NULL, returns
   *   all records.
   * @param bool $convert
   *   Defaults to FALSE. If TRUE, the argument string will be converted
   *   to an array.
   *
   * @return array
   *   An array with information about the indexed arguments.
   */
  public function getArgumentsFromIndex(?ConditionInterface $condition = NULL, ?int $limit = NULL, bool $convert = FALSE): array {
    $query = $this->database->select('simple_sitemap_views', 'ssv');
    $query->addField('ssv', 'id');
    $query->addField('ssv', 'view_id');
    $query->addField('ssv', 'display_id');
    $query->addField('ssv', 'arguments_values', 'arguments');

    if ($condition !== NULL) {
      $query->condition($condition);
    }
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $rows = $query->execute()->fetchAll();
    $arguments = [];

    foreach ($rows as $row) {
      $arguments[$row->id] = [
        'view_id' => $row->view_id,
        'display_id' => $row->display_id,
        'arguments' => $convert ? $this->convertArgumentsStringToArray($row->arguments) : $row->arguments,
      ];
    }

    return $arguments;
  }

  /**
   * Get the number of rows in the index.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface|null $condition
   *   The query conditions.
   *
   * @return int
   *   The number of rows.
   */
  public function getArgumentsFromIndexCount(?ConditionInterface $condition = NULL): int {
    $query = $this->database->select('simple_sitemap_views', 'ssv');

    if ($condition !== NULL) {
      $query->condition($condition);
    }

    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Returns the ID of the record in the index for the specified position.
   *
   * @param int $position
   *   Position of the record.
   * @param \Drupal\Core\Database\Query\ConditionInterface|null $condition
   *   The query conditions.
   *
   * @return int|bool
   *   The ID of the record, or FALSE if there is no specified position.
   */
  public function getIndexIdByPosition(int $position, ?ConditionInterface $condition = NULL) {
    $query = $this->database->select('simple_sitemap_views', 'ssv');
    $query->addField('ssv', 'id');

    if ($condition !== NULL) {
      $query->condition($condition);
    }

    $query->orderBy('id');
    $query->range($position - 1, 1);

    return $query->execute()->fetchField();
  }

  /**
   * Remove arguments from index.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface|null $condition
   *   The query conditions.
   */
  public function removeArgumentsFromIndex(?ConditionInterface $condition = NULL): void {
    if ($condition === NULL) {
      // If there are no conditions, use the TRUNCATE query.
      $query = $this->database->truncate('simple_sitemap_views');
    }
    else {
      // Otherwise, use the DELETE query.
      $query = $this->database->delete('simple_sitemap_views');
      $query->condition($condition);
    }
    $query->execute();
  }

  /**
   * Returns an array of view displays that use the route.
   *
   * @param \Drupal\views\ViewEntityInterface $view_entity
   *   The config entity in which the view is stored.
   *
   * @return array
   *   Array of display identifiers.
   */
  public function getRouterDisplayIds(ViewEntityInterface $view_entity): array {
    $display_plugins = $this->getRouterDisplayPluginIds();

    $filter_callback = function (array $display) use ($display_plugins) {
      return !empty($display['display_plugin']) && in_array($display['display_plugin'], $display_plugins, TRUE);
    };

    return array_keys(array_filter($view_entity->get('display'), $filter_callback));
  }

  /**
   * Returns an array of executable views whose current display is indexable.
   *
   * @return \Drupal\views\ViewExecutable[]
   *   An array of ViewExecutable instances.
   */
  public function getIndexableViews(): array {
    // Check that views support is enabled.
    if (!$this->isEnabled()) {
      return [];
    }

    // Load views with display plugins that use the route.
    $query = $this->viewStorage->getQuery()
      ->condition('status', TRUE)
      ->condition("display.*.display_plugin", $this->getRouterDisplayPluginIds(), 'IN')
      ->accessCheck(TRUE);
    $view_ids = $query->execute();

    // If there are no such views, then return an empty array.
    if (empty($view_ids)) {
      return [];
    }

    $indexable_views = [];
    /** @var \Drupal\views\ViewEntityInterface $view_entity */
    foreach ($this->viewStorage->loadMultiple($view_ids) as $view_entity) {
      foreach ($this->getRouterDisplayIds($view_entity) as $display_id) {
        $view = Views::executableFactory()->get($view_entity);

        // Ensure the display was correctly set.
        if (!$view->setDisplay($display_id)) {
          $view->destroy();
          continue;
        }

        // Check that the display is enabled and indexed.
        if ($view->display_handler->isEnabled() && $this->getIndexableSitemaps($view)) {
          $indexable_views[] = $view;
        }
      }
    }

    return $indexable_views;
  }

  /**
   * Returns an array of indexable sitemaps for view display.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A view executable instance.
   * @param string|null $display_id
   *   The display id. If empty uses the current display.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   *   An array of sitemap entities.
   */
  public function getIndexableSitemaps(ViewExecutable $view, ?string $display_id = NULL): array {
    // Ensure the display was correctly set.
    if (!$view->setDisplay($display_id)) {
      return [];
    }

    $sitemaps = $this->getSitemaps();
    foreach ($sitemaps as $variant => $sitemap) {
      if (!$this->getSitemapSettings($view, $variant)) {
        unset($sitemaps[$variant]);
      }
    }

    return $sitemaps;
  }

  /**
   * Returns an array of correctly configured sitemaps.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   *   An array of sitemap entities.
   */
  public function getSitemaps(): array {
    $sitemaps = SimpleSitemap::loadMultiple();

    /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $sitemap */
    foreach ($sitemaps as $variant => $sitemap) {
      if (!$sitemap->getType()->hasUrlGenerator('views')) {
        unset($sitemaps[$variant]);
      }
    }

    return $sitemaps;
  }

  /**
   * Creates tasks in the garbage collection queue.
   */
  public function executeGarbageCollection() {
    // The task queue of garbage collection in the arguments index.
    $queue = $this->queueFactory->get('simple_sitemap.views.garbage_collector');

    // Check that the queue is empty.
    if ($queue->numberOfItems()) {
      return;
    }

    // Get identifiers of indexed views.
    $query = $this->database->select('simple_sitemap_views', 'ssv');
    $query->addField('ssv', 'view_id');
    $query->distinct();
    $result = $query->execute()->fetchCol();

    // Create a garbage collection tasks.
    foreach ($result as $view_id) {
      $queue->createItem(['view_id' => $view_id]);
    }
  }

  /**
   * Get variations for string representation of arguments.
   *
   * @param array $args
   *   Array of arguments.
   *
   * @return array
   *   Array of variations of the string representation of arguments.
   */
  public function getArgumentsStringVariations(array $args): array {
    $variations = [];

    for ($length = 1; $length <= count($args); $length++) {
      $args_slice = array_slice($args, 0, $length);
      $variations[] = $this->convertArgumentsArrayToString($args_slice);
    }

    return $variations;
  }

  /**
   * Converts an array of arguments to a string.
   *
   * @param array $args
   *   Array of arguments to convert.
   *
   * @return string
   *   A string representation of the arguments.
   */
  protected function convertArgumentsArrayToString(array $args): string {
    return implode(self::ARGUMENT_SEPARATOR, $args);
  }

  /**
   * Converts a string with arguments to an array.
   *
   * @param string $args
   *   A string representation of the arguments to convert.
   *
   * @return array
   *   Array of arguments.
   */
  protected function convertArgumentsStringToArray($args): array {
    return explode(self::ARGUMENT_SEPARATOR, $args);
  }

  /**
   * Get all display plugins that use the route.
   *
   * @return array
   *   An array with plugin identifiers.
   */
  protected function getRouterDisplayPluginIds(): array {
    static $plugin_ids = [];

    if (empty($plugin_ids)) {
      $display_plugins = Views::pluginManager('display')->getDefinitions();

      // Get all display plugins that use the route.
      foreach ($display_plugins as $plugin_id => $definition) {
        if (!empty($definition['uses_route'])) {
          $plugin_ids[$plugin_id] = $plugin_id;
        }
      }
    }

    return $plugin_ids;
  }

}
