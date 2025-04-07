<?php

declare(strict_types=1);

namespace Drupal\project_browser_test\Plugin\ProjectBrowserSource;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSource\SortHelper;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\BooleanFilter;
use Drupal\project_browser\ProjectBrowser\Filter\MultipleChoiceFilter;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

// cspell:ignore Edealing

/**
 * Database driven plugin.
 */
#[ProjectBrowserSource(
  id: 'project_browser_test_mock',
  label: new TranslatableMarkup('Project Browser Mock Plugin'),
  description: new TranslatableMarkup('Gets project and filters information from a database'),
  local_task: [
    'title' => new TranslatableMarkup('Browse'),
  ],
)]
final class ProjectBrowserTestMock extends ProjectBrowserSourceBase {

  /**
   * This is what the Mock understands as "Covered" modules.
   *
   * @var array
   */
  const COVERED_VALUES = ['covered'];

  /**
   * This is what the Mock understands as "Active" modules.
   *
   * @var array
   */
  const ACTIVE_VALUES = [9988, 13030];

  /**
   * This is what the Mock understands as "Maintained" modules.
   *
   * @var array
   */
  const MAINTAINED_VALUES = [13028, 19370, 9990];

  /**
   * An error message to flag when querying.
   *
   * @var string|null
   */
  public static ?string $resultsError = NULL;

  /**
   * Constructor for mock API.
   *
   * @param array $configuration
   *   The source configuration.
   * @param string $plugin_id
   *   The identifier for the plugin.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\State\StateInterface $state
   *   The session state.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LoggerInterface $logger,
    private readonly Connection $database,
    private readonly StateInterface $state,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('project_browser'),
      $container->get(Connection::class),
      $container->get(StateInterface::class),
      $container->get(ModuleHandlerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    $options = parent::getSortOptions();
    unset($options['best_match']);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSecurityCoverages(): array {
    return [
      ['id' => 'covered', 'name' => 'Covered'],
      ['id' => 'not-covered', 'name' => 'Not covered'],
    ];
  }

  /**
   * Convert the sort entry within the query from received to expected by DB.
   *
   * @param array $query
   *   Query array to transform.
   */
  protected function convertSort(array &$query): void {
    if (!empty($query['sort'])) {
      $options_available = $this->getSortOptions();
      if (!in_array($query['sort'], array_keys($options_available))) {
        unset($query['sort']);
      }
      else {
        // Valid value.
        switch ($query['sort']) {
          case 'usage_total':
          case 'best_match':
            $query['sort'] = 'project_usage_total';
            $query['direction'] = 'DESC';
            break;

          case 'a_z':
            $query['sort'] = 'title';
            $query['direction'] = 'ASC';
            break;

          case 'z_a':
            $query['sort'] = 'title';
            $query['direction'] = 'DESC';
            break;

          case 'created':
            $query['sort'] = 'created';
            $query['direction'] = 'DESC';
            break;

        }
      }
    }
  }

  /**
   * Converts the maintenance entry from received to expected by DB.
   *
   * @param array $query
   *   Query array to transform.
   */
  protected function convertMaintenance(array &$query): void {
    if (!empty($query['maintenance_status'])) {
      $query['maintenance_status'] = self::MAINTAINED_VALUES;
    }
    else {
      unset($query['maintenance_status']);
    }
  }

  /**
   * Converts the development entry from received to expected by DB.
   *
   * @param array $query
   *   Query array to transform.
   */
  protected function convertDevelopment(array &$query): void {
    if (!empty($query['development_status'])) {
      $query['development_status'] = self::ACTIVE_VALUES;
    }
    else {
      unset($query['development_status']);
    }
  }

  /**
   * Converts the security entry from received to expected by DB.
   *
   * @param array $query
   *   Query array to transform.
   */
  protected function convertSecurity(array &$query): void {
    if (!empty($query['security_advisory_coverage'])) {
      $query['security_advisory_coverage'] = self::COVERED_VALUES;
    }
    else {
      $query['security_advisory_coverage'] = array_column(
        $this->getSecurityCoverages(),
        'id'
      );
    }
  }

  /**
   * Convert the search values from available ones to expected ones.
   *
   * The values that were given as available for the search need to be the
   * actual values that will be queried within the search function.
   *
   * @param array $query
   *   Query parameters to check.
   *
   * @return array
   *   Query parameters converted to the values expected by the search function.
   */
  protected function convertQueryOptions(array $query = []): array {
    $this->convertSort($query);
    $this->convertMaintenance($query);
    $this->convertDevelopment($query);
    $this->convertSecurity($query);

    return $query;
  }

  /**
   * Returns category data keyed by category ID.
   *
   * @return array
   *   The category ID and name, keyed by ID.
   */
  protected function getCategoryData(): array {
    $module_path = $this->moduleHandler->getModule('project_browser')->getPath();
    $contents = file_get_contents($module_path . '/tests/fixtures/category_list.json');
    assert(is_string($contents));
    $category_list = Json::decode($contents) ?? [];
    $categories = [];
    foreach ($category_list as $category) {
      $categories[$category['tid']] = [
        'id' => $category['tid'],
        'name' => $category['name'],
      ];
    }
    return $categories;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    $filters_to_define = $this->state->get('filters_to_define');
    if ($filters_to_define !== NULL) {
      return $filters_to_define;
    }

    $filters = [
      'search' => new TextFilter('', $this->t('Search')),
    ];

    $categories = array_values($this->getCategoryData());
    $categories = array_combine(
      array_column($categories, 'id'),
      array_column($categories, 'name'),
    );
    $filters['categories'] = new MultipleChoiceFilter($categories, [], $this->t('Categories'), $this->t('Categories'));

    $filters['security_advisory_coverage'] = new BooleanFilter(
      TRUE,
      $this->t('Only show projects covered by a security policy'),
    );
    $filters['maintenance_status'] = new BooleanFilter(
      TRUE,
      $this->t('Only show actively maintained projects'),
    );
    $filters['development_status'] = new BooleanFilter(
      FALSE,
      $this->t('Only show projects under active development'),
    );
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []) : ProjectsResultsPage {
    $api_response = $this->fetchProjects($query);
    $categories = $this->getCategoryData();

    $returned_list = [];
    if (is_array($api_response)) {
      foreach ($api_response['list'] as $project_data) {
        $avatar_url = 'https://git.drupalcode.org/project/' . $project_data['field_project_machine_name'] . '/-/avatar';

        $returned_list[] = new Project(
          logo: Url::fromUri($avatar_url),
          // Mock projects are filtered and made sure that they are compatible
          // before we even put them in the database, but allow that to be
          // overridden for testing.
          isCompatible: $this->state->get('project_browser_test_mock isCompatible', TRUE),
          isMaintained: in_array($project_data['maintenance_status'], self::MAINTAINED_VALUES),
          isCovered: in_array($project_data['field_security_advisory_coverage'], self::COVERED_VALUES),
          projectUsageTotal: array_reduce($project_data['project_data']['project_usage'] ?? [], fn($total, $project_usage): int => $total + $project_usage) ?: 0,
          machineName: $project_data['field_project_machine_name'],
          body: $this->relativeToAbsoluteUrls($project_data['project_data']['body'], 'https://www.drupal.org'),
          title: $project_data['title'],
          packageName: 'drupal/' . $project_data['field_project_machine_name'],
          url: Url::fromUri('https://www.drupal.org/project/' . $project_data['field_project_machine_name']),
          // Add name property to each category, so it can be rendered.
          categories: array_map(fn($category): array => $categories[$category['id']] ?? [], $project_data['project_data']['taxonomy_vocabulary_3'] ?? []),
          images: array_map(
            function (array $image): array {
              $image['file'] = Url::fromUri($image['file']['uri']);
              return $image;
            },
            $project_data['project_data']['field_project_images'] ?? [],
          ),
          id: $project_data['field_project_machine_name'],
        );
      }
    }

    if (array_key_exists('order', $this->configuration)) {
      SortHelper::sortInDefinedOrder($returned_list, $this->configuration['order']);
    }
    return $this->createResultsPage($returned_list, (int) ($api_response['total_results'] ?? 0), static::$resultsError);
  }

  /**
   * Fetches the projects from the mock backend.
   *
   * Here, we're querying the local database, populated from the fixture.
   */
  protected function fetchProjects(array $query): bool|array {
    $query = $this->convertQueryOptions($query);
    try {
      $db_query = $this->database->select('project_browser_projects', 'pbp')
        ->fields('pbp')
        ->condition('pbp.status', 1);

      if (array_key_exists('machine_name', $query)) {
        $db_query->condition('field_project_machine_name', $query['machine_name']);
      }

      if (array_key_exists('sort', $query) && !empty($query['sort'])) {
        $sort = $query['sort'];
        $direction = (array_key_exists('direction', $query) && $query['direction'] == 'ASC') ? 'ASC' : 'DESC';
        $db_query->orderBy($sort, $direction);
      }
      else {
        // Default order.
        $db_query->orderBy('project_usage_total', 'DESC');
      }

      // Filter by maintenance status.
      if (array_key_exists('maintenance_status', $query)) {
        $db_query->condition('maintenance_status', $query['maintenance_status'], 'IN');
      }

      // Filter by development status.
      if (array_key_exists('development_status', $query)) {
        $db_query->condition('development_status', $query['development_status'], 'IN');
      }

      // Filter by security advisory coverage.
      if (array_key_exists('security_advisory_coverage', $query)) {
        $db_query->condition('field_security_advisory_coverage', $query['security_advisory_coverage'], 'IN');
      }

      // Filter by category.
      if (!empty($query['categories'])) {
        $tids = explode(',', $query['categories']);
        $db_query->join('project_browser_categories', 'cat', 'pbp.nid = cat.pid');
        $db_query->condition('cat.tid', $tids, 'IN');
      }

      // Filter by search term.
      if (array_key_exists('search', $query)) {
        $search = $query['search'];
        $db_query->condition('pbp.project_data', "%$search%", 'LIKE');
      }
      $db_query->groupBy('pbp.nid');

      // If there is a specified limit, then this is a list of multiple
      // projects.
      $total_results = $db_query->countQuery()
        ->execute()
        ?->fetchField();
      $offset = $query['page'] ?? 0;
      $limit = $query['limit'] ?? 50;
      $db_query->range($limit * $offset, $limit);
      $result = $db_query
        ->execute()
        ?->fetchAll() ?? [];
      $db_projects = array_map(function ($project_data) {
        $data = (array) $project_data;
        $data['project_data'] = unserialize($project_data->project_data);
        return $data;
      }, $result);

      if (count($db_projects) > 0) {
        $drupal_org_response['list'] = $db_projects;
        $drupal_org_response['total_results'] = $total_results;
        return $drupal_org_response;
      }

      return FALSE;
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return FALSE;
    }
  }

  /**
   * Convert relative URLs found in the body to absolute URLs.
   *
   * @param array $body
   *   Body array field containing summary and value properties.
   * @param string $base_url
   *   Base URL to prepend to relative links.
   *
   * @return array
   *   Body array with relative URLs converted to absolute ones.
   */
  protected function relativeToAbsoluteUrls(array $body, string $base_url): array {
    if (empty($body['value'])) {
      $body['value'] = $body['summary'] ?? '';
    }
    $body['value'] = Html::transformRootRelativeUrlsToAbsolute($body['value'], $base_url);
    return $body;
  }

}
