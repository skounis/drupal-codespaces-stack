<?php

declare(strict_types=1);

namespace Drupal\search_api\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Contrib\RowsOfMultiValueFields;
use Drupal\search_api\Utility\CommandHelper;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Help;
use Drush\Attributes\Usage;
use Drush\Attributes\FieldLabels;
use Drush\Attributes\DefaultFields;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

// cspell:ignore disa

/**
 * Defines Drush commands for the Search API.
 */
final class SearchApiDrushCommands extends DrushCommands {

  /**
   * The command helper.
   *
   * @var \Drupal\search_api\Utility\CommandHelper
   */
  protected $commandHelper;

  /**
   * Constructs a SearchApiCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler, EventDispatcherInterface $eventDispatcher) {
    parent::__construct();
    $this->commandHelper = new CommandHelper($entityTypeManager, $moduleHandler, $eventDispatcher, 'dt');
  }

  /**
   * Instantiates a new instance of this class.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return static
   *   A new class instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   * @throws \Psr\Container\ContainerExceptionInterface
   *   Thrown if some required services are not registered.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger): void {
    parent::setLogger($logger);
    $this->commandHelper->setLogger($logger);
  }

  /**
   * Lists all search indexes.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an index has a server which couldn't be loaded.
   *
   * @command search-api:list
   * @aliases sapi-l,search-api-list
   *
   * @usage drush search-api:list
   *   List all search indexes.
   *
   * @field-labels
   *   id: ID
   *   name: Name
   *   server: Server ID
   *   serverName: Server name
   *   types: Type IDs
   *   typeNames: Type names
   *   status: Status
   *   limit: Limit
   *
   * @default-fields id,name,serverName,typeNames,status,limit
   */
  #[Command(name: 'search-api:list', aliases: ['sapi-l', 'search-api-list'])]
  #[Help(description: 'Lists all search indexes.')]
  #[Usage(name: 'drush search-api:list', description: 'List all search indexes.')]
  #[FieldLabels(labels: ['id' => 'ID', 'name' => 'Name', 'server' => 'Server ID', 'serverName' => 'Server name', 'types' => 'Type IDs', 'typeNames' => 'Type names', 'status' => 'Status', 'limit' => 'Limit'])]
  #[DefaultFields(fields: ['id', 'name', 'serverName', 'typeNames', 'status', 'limit'])]
  public function listCommand(): RowsOfFields {
    $rows = $this->commandHelper->indexListCommand();

    return new RowsOfMultiValueFields($rows);
  }

  /**
   * Enables one disabled search index.
   *
   * @param string $indexId
   *   A search index ID.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   *
   * @command search-api:enable
   * @aliases sapi-en,search-api-enable
   *
   * @usage drush search-api:enable node_index
   *   Enable the search index with the ID node_index.
   */
  #[Command(name: 'search-api:enable', aliases: ['sapi-en', 'search-api-enable'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Enables one disabled search index.')]
  #[Usage(name: 'drush search-api:enable node_index', description: 'Enable the search index with the ID node_index.')]
  public function enable(string $indexId): void {
    $this->commandHelper->enableIndexCommand([$indexId]);
  }

  /**
   * Enables all disabled search indexes.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   *
   * @command search-api:enable-all
   * @aliases sapi-ena,search-api-enable-all
   *
   * @usage drush search-api:enable-all
   *   Enable all disabled indexes.
   * @usage drush sapi-ena
   *   Alias to enable all disabled indexes.
   */
  #[Command(name: 'search-api:enable-all', aliases: ['sapi-ena', 'search-api-enable-all'])]
  #[Help(description: 'Enables all disabled search indexes.')]
  #[Usage(name: 'drush search-api:enable-all', description: 'Enable all disabled indexes.')]
  #[Usage(name: 'drush sapi-ena', description: 'Alias to enable all disabled indexes.')]
  public function enableAll(): void {
    $this->commandHelper->enableIndexCommand();
  }

  /**
   * Disables one or more enabled search indexes.
   *
   * @param string $indexId
   *   A search index ID.
   *
   * @throws \Exception
   *   If no indexes are defined or no index has been passed.
   *
   * @command search-api:disable
   * @aliases sapi-dis,search-api-disable
   *
   * @usage drush search-api:disable node_index
   *   Disable the search index with the ID node_index.
   * @usage drush sapi-dis node_index
   *   Alias to disable the search index with the ID node_index.
   */
  #[Command(name: 'search-api:disable', aliases: ['sapi-dis', 'search-api-disable'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Disables one or more enabled search indexes.')]
  #[Usage(name: 'drush search-api:disable node_index', description: 'Disable the search index with the ID node_index.')]
  #[Usage(name: 'drush sapi-dis node_index', description: 'Alias to disable the search index with the ID node_index.')]
  public function disable(string $indexId): void {
    $this->commandHelper->disableIndexCommand([$indexId]);
  }

  /**
   * Disables all enabled search indexes.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   *
   * @command search-api:disable-all
   * @aliases sapi-disa,search-api-disable-all
   *
   * @usage drush search-api:disable-all
   *   Disable all enabled indexes.
   * @usage drush sapi-disa
   *   Alias to disable all enabled indexes.
   */
  #[Command(name: 'search-api:disable-all', aliases: ['sapi-disa', 'search-api-disable-all'])]
  #[Help(description: 'Disables all enabled search indexes.')]
  #[Usage(name: 'drush search-api:disable-all', description: 'Disable all enabled indexes.')]
  #[Usage(name: 'drush sapi-disa', description: 'Alias to disable all enabled indexes.')]
  public function disableAll(): void {
    $this->commandHelper->disableIndexCommand();
  }

  /**
   * Shows the status of one or all search indexes.
   *
   * @param string|null $indexId
   *   (optional) A search index ID, or NULL to show the status of all indexes.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set.
   *
   * @command search-api:status
   * @aliases sapi-s,search-api-status
   *
   * @usage drush search-api:status
   *   Show the status of all search indexes.
   * @usage drush sapi-s
   *   Alias to show the status of all search indexes.
   * @usage drush sapi-s node_index
   *   Show the status of the search index with the ID node_index.
   *
   * @field-labels
   *   id: ID
   *   name: Name
   *   complete: % Complete
   *   indexed: Indexed
   *   total: Total
   */
  #[Command(name: 'search-api:status', aliases: ['sapi-s', 'search-api-status'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Shows the status of one or all search indexes.')]
  #[Usage(name: 'drush search-api:status', description: 'Show the status of all search indexes.')]
  #[Usage(name: 'drush sapi-s', description: 'Alias to show the status of all search indexes.')]
  #[Usage(name: 'drush sapi-s node_index', description: 'Show the status of the search index with the ID node_index.')]
  #[FieldLabels(labels: ['id' => 'ID', 'name' => 'Name', 'complete' => '% Complete', 'indexed' => 'Indexed', 'total' => 'Total'])]
  public function status(?string $indexId = NULL): RowsOfFields {
    $rows = $this->commandHelper->indexStatusCommand([$indexId]);
    return new RowsOfFields($rows);
  }

  /**
   * Indexes items for one or all enabled search indexes.
   *
   * @param string|null $indexId
   *   (optional) A search index ID, or NULL to index items for all enabled
   *   indexes.
   * @param array $options
   *   (optional) An array of options.
   *
   * @throws \Exception
   *   If a batch process could not be created.
   *
   * @command search-api:index
   * @aliases sapi-i,search-api-index
   *
   * @option limit
   *   The maximum number of items to index. Set to 0 to index all items.
   *   Defaults to 0 (index all).
   * @option batch-size
   *   The maximum number of items to index per batch run. Defaults to the "Cron
   *   batch size" setting of the index if omitted or explicitly set to 0. Set
   *   to a negative value to index all items in a single batch (not
   *   recommended).
   *
   * @usage drush search-api:index
   *   Index all items for all enabled indexes.
   * @usage drush sapi-i
   *   Alias to index all items for all enabled indexes.
   * @usage drush sapi-i node_index
   *   Index all items for the index with the ID node_index.
   * @usage drush sapi-i --limit=100 node_index
   *   Index a maximum number of 100 items for the index with the ID node_index.
   * @usage drush sapi-i --limit=100 --batch-size=10 node_index
   *   Index a maximum number of 100 items (10 items per batch run) for the
   *   index with the ID node_index.
   */
  #[Command(name: 'search-api:index', aliases: ['sapi-i', 'search-api-index'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Indexes items for one or all enabled search indexes.')]
  #[Usage(name: 'drush search-api:index', description: 'Index all items for all enabled indexes.')]
  #[Usage(name: 'drush sapi-i', description: 'Alias to index all items for all enabled indexes.')]
  #[Usage(name: 'drush sapi-i node_index', description: 'Index all items for the index with the ID node_index.')]
  #[Usage(name: 'drush sapi-i --limit=100 node_index', description: 'Index a maximum number of 100 items for the index with the ID node_index.')]
  #[Usage(name: 'drush sapi-i --limit=100 --batch-size=10 node_index', description: 'Index a maximum number of 100 items (10 items per batch run) for the index with the ID node_index.')]
  public function index(?string $indexId = NULL, array $options = ['limit' => NULL, 'batch-size' => NULL]): void {
    $limit = $options['limit'];
    $batch_size = $options['batch-size'];
    $process_batch = $this->commandHelper->indexItemsToIndexCommand([$indexId], $limit, $batch_size);

    if ($process_batch === TRUE) {
      drush_backend_batch_process();
    }
  }

  /**
   * Marks one or all indexes for reindexing without deleting existing data.
   *
   * @param string|null $indexId
   *   The machine name of an index. Optional. If missed, will schedule all
   *   search indexes for reindexing.
   * @param array $options
   *   An array of options.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set, or some
   *   other internal error occurred.
   *
   * @command search-api:reset-tracker
   * @aliases search-api-mark-all,search-api-reindex,sapi-r,search-api-reset-tracker
   *
   * @option entity-types List of entity type ids to reset tracker for.
   *
   * @usage drush search-api:reset-tracker
   *   Schedule all search indexes for reindexing.
   * @usage drush sapi-r
   *   Alias to schedule all search indexes for reindexing .
   * @usage drush sapi-r node_index
   *   Schedule the search index with the ID node_index for reindexing.
   */
  #[Command(name: 'search-api:reset-tracker', aliases: ['search-api-mark-all', 'search-api-reindex', 'sapi-r', 'search-api-reset-tracker'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Marks one or all indexes for reindexing without deleting existing data.')]
  #[Usage(name: 'drush search-api:reset-tracker', description: 'Schedule all search indexes for reindexing.')]
  #[Usage(name: 'drush sapi-r', description: 'Alias to schedule all search indexes for reindexing .')]
  #[Usage(name: 'drush sapi-r node_index', description: 'Schedule the search index with the ID node_index for reindexing.')]
  public function resetTracker(?string $indexId = NULL, array $options = ['entity-types' => []]): void {
    $this->commandHelper->resetTrackerCommand([$indexId], $options['entity-types']);
  }

  /**
   * Rebuilds the trackers for one or all indexes.
   *
   * @param string|null $indexId
   *   The machine name of an index. Optional. If missed, will rebuild the
   *   trackers of all indexes.
   *
   * @command search-api:rebuild-tracker
   * @aliases sapi-rt,search-api-rebuild-tracker
   *
   * @usage drush search-api:rebuild-tracker
   *   Rebuild the trackers of all search indexes.
   * @usage drush sapi-rt
   *   Alias for rebuilding the trackers of all search indexes.
   * @usage drush sapi-rt node_index
   *   Rebuild the tracker of the search index with the ID node_index.
   */
  #[Command(name: 'search-api:rebuild-tracker', aliases: ['sapi-rt', 'search-api-rebuild-tracker'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Rebuilds the trackers for one or all indexes.')]
  #[Usage(name: 'drush search-api:rebuild-tracker', description: 'Rebuild the trackers of all search indexes.')]
  #[Usage(name: 'drush sapi-rt', description: 'Alias for rebuilding the trackers of all search indexes.')]
  #[Usage(name: 'drush sapi-rt node_index', description: 'Rebuild the tracker of the search index with the ID node_index.')]
  public function rebuildTracker(?string $indexId = NULL): void {
    $this->commandHelper->rebuildTrackerCommand([$indexId]);
  }

  /**
   * Clears one or all search indexes and marks them for reindexing.
   *
   * @param string|null $indexId
   *   The machine name of an index. Optional. If missed all search indexes will
   *   be cleared.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set, or some
   *   other internal error occurred.
   *
   * @command search-api:clear
   * @aliases sapi-c,search-api-clear
   *
   * @usage drush search-api:clear
   *   Clear all search indexes.
   * @usage drush sapi-c
   *   Alias to clear all search indexes.
   * @usage drush sapi-c node_index
   *   Clear the search index with the ID node_index.
   */
  #[Command(name: 'search-api:clear', aliases: ['sapi-c', 'search-api-clear'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Help(description: 'Clears one or all search indexes and marks them for reindexing.')]
  #[Usage(name: 'drush search-api:clear', description: 'Clear all search indexes.')]
  #[Usage(name: 'drush sapi-c', description: 'Alias to clear all search indexes.')]
  #[Usage(name: 'drush search-api:clear node_index', description: 'Clear the search index with the ID node_index.')]
  public function clear(?string $indexId = NULL): void {
    $this->commandHelper->clearIndexCommand([$indexId]);
  }

  /**
   * Searches for a keyword or phrase in a given index.
   *
   * @param string $indexId
   *   The machine name of an index.
   * @param string $keyword
   *   The keyword to look for.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if no search query could be created for the given index, for
   *   example because it is disabled or its server could not be loaded.
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if searching failed for any reason.
   *
   * @command search-api:search
   * @aliases sapi-search,search-api-search
   *
   * @usage drush search-api:search node_index title
   *   Search for "title" inside the "node_index" index.
   * @usage drush sapi-search node_index title
   *   Alias to search for "title" inside the "node_index" index.
   *
   * @field-labels
   *   id: ID
   *   label: Label
   */
  #[Command(name: 'search-api:search', aliases: ['sapi-search', 'search-api-search'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Argument(name: 'keyword', description: 'The keyword(s) to search for')]
  #[Help(description: 'Searches for a keyword or phrase in a given index.')]
  #[Usage(name: 'drush search-api:search node_index title', description: 'Search for "title" inside the "node_index" index.')]
  #[Usage(name: 'drush sapi-search node_index title', description: 'Alias to search for "title" inside the "node_index" index.')]
  #[FieldLabels(labels: ['id' => 'ID', 'label' => 'Label'])]
  public function search(string $indexId, string $keyword): RowsOfFields {
    $rows = $this->commandHelper->searchIndexCommand($indexId, $keyword);

    return new RowsOfFields($rows);
  }

  /**
   * Lists all search servers.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The table rows.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no servers could be loaded.
   *
   * @command search-api:server-list
   * @aliases sapi-sl,search-api-server-list
   *
   * @usage drush search-api:server-list
   *   List all search servers.
   * @usage drush sapi-sl
   *   Alias to list all search servers.
   *
   * @field-labels
   *   id: ID
   *   name: Name
   *   status: Status
   */
  #[Command(name: 'search-api:server-list', aliases: ['sapi-sl', 'search-api-server-list'])]
  #[Help(description: 'Lists all search servers.')]
  #[Usage(name: 'drush search-api:server-list', description: 'List all search servers.')]
  #[Usage(name: 'drush sapi-sl', description: 'Alias to list all search servers.')]
  #[FieldLabels(labels: ['id' => 'ID', 'name' => 'Name', 'status' => 'Status'])]
  public function serverList(): RowsOfFields {
    $rows = $this->commandHelper->serverListCommand();

    return new RowsOfFields($rows);
  }

  /**
   * Enables a search server.
   *
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if the server couldn't be loaded.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if an internal error occurred when saving the server.
   *
   * @command search-api:server-enable
   * @aliases sapi-se,search-api-server-enable
   *
   * @usage drush search-api:server-enable my_solr_server
   *   Enable the my_solr_server search server.
   * @usage drush sapi-se my_solr_server
   *   Alias to enable the my_solr_server search server.
   */
  #[Command(name: 'search-api:server-enable', aliases: ['sapi-se', 'search-api-server-enable'])]
  #[Argument(name: 'serverId', description: 'The ID of the search server')]
  #[Help(description: 'Enables a search server.')]
  #[Usage(name: 'drush search-api:server-enable my_solr_server', description: 'Enable the my_solr_server search server.')]
  #[Usage(name: 'drush sapi-se my_solr_server', description: 'Alias to enable the my_solr_server search server.')]
  public function serverEnable(string $serverId): void {
    $this->commandHelper->enableServerCommand($serverId);
  }

  /**
   * Disables a search server.
   *
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if the server couldn't be loaded.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if an internal error occurred when saving the server.
   *
   * @command search-api:server-disable
   * @aliases sapi-sd,search-api-server-disable
   *
   * @usage drush search-api:server-disable
   *   Disable the my_solr_server search server.
   * @usage drush sapi-sd
   *   Alias to disable the my_solr_server search server.
   */
  #[Command(name: 'search-api:server-disable', aliases: ['sapi-sd', 'search-api-server-disable'])]
  #[Argument(name: 'serverId', description: 'The ID of the search server')]
  #[Help(description: 'Disables a search server.')]
  #[Usage(name: 'drush search-api:server-disable', description: 'Disable the my_solr_server search server.')]
  #[Usage(name: 'drush sapi-sd', description: 'Alias to disable the my_solr_server search server.')]
  public function serverDisable(string $serverId): void {
    $this->commandHelper->disableServerCommand($serverId);
  }

  /**
   * Clears all search indexes on the given search server.
   *
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if the server couldn't be loaded.
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if one of the affected indexes had an invalid tracker set, or some
   *   other internal error occurred.
   *
   * @command search-api:server-clear
   * @aliases sapi-sc,search-api-server-clear
   *
   * @usage drush search-api:server-clear my_solr_server
   *   Clear all search indexes on the search server my_solr_server.
   * @usage drush sapi-sc my_solr_server
   *   Alias to clear all search indexes on the search server my_solr_server.
   */
  #[Command(name: 'search-api:server-clear', aliases: ['sapi-sc', 'search-api-server-clear'])]
  #[Argument(name: 'serverId', description: 'The ID of the search server')]
  #[Help(description: 'Clears all search indexes on the given search server.')]
  #[Usage(name: 'drush search-api:server-clear my_solr_server', description: 'Clear all search indexes on the search server my_solr_server.')]
  #[Usage(name: 'drush sapi-sc my_solr_server', description: 'Alias to clear all search indexes on the search server my_solr_server.')]
  public function serverClear(string $serverId): void {
    $this->commandHelper->clearServerCommand($serverId);
  }

  /**
   * Sets the search server used by a given index.
   *
   * @param string $indexId
   *   The machine name of an index.
   * @param string $serverId
   *   The machine name of a server.
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   *
   * @command search-api:set-index-server
   * @aliases sapi-sis,search-api-set-index-server
   *
   * @usage drush search-api:set-index-server default_node_index my_solr_server
   *   Set the default_node_index index to used the my_solr_server server.
   * @usage drush sapi-sis default_node_index my_solr_server
   *   Alias to set the default_node_index index to used the my_solr_server
   *   server.
   */
  #[Command(name: 'search-api:set-index-server', aliases: ['sapi-sis', 'search-api-set-index-server'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Argument(name: 'serverId', description: 'The ID of the search server')]
  #[Help(description: 'Sets the search server used by a given index.')]
  #[Usage(name: 'drush search-api:set-index-server default_node_index my_solr_server', description: 'Set the default_node_index index to used the my_solr_server server.')]
  #[Usage(name: 'drush sapi-sis default_node_index my_solr_server', description: 'Alias to set the default_node_index index to used the my_solr_server server.')]
  public function setIndexServer(string $indexId, string $serverId): void {
    $this->commandHelper->setIndexServerCommand($indexId, $serverId);
  }

}
