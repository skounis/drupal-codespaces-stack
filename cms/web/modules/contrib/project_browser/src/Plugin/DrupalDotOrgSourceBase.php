<?php

namespace Drupal\project_browser\Plugin;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ExtensionVersion;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\project_browser\ProjectBrowser\Filter\BooleanFilter;
use Drupal\project_browser\ProjectBrowser\Filter\MultipleChoiceFilter;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a base class for sources that interact with the Drupal.org API.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
abstract class DrupalDotOrgSourceBase extends ProjectBrowserSourceBase implements ContainerFactoryPluginInterface {

  /**
   * Main domain endpoint.
   *
   * @const string
   */
  public const DRUPAL_ORG_ENDPOINT = 'https://www.drupal.org';

  /**
   * Endpoint to query data from.
   *
   * @const string
   */
  public const JSONAPI_ENDPOINT = self::DRUPAL_ORG_ENDPOINT . '/jsonapi';

  /**
   * Endpoint to query modules.
   *
   * @const string
   */
  protected const JSONAPI_MODULES_ENDPOINT = self::JSONAPI_ENDPOINT . '/index/project_modules';

  /**
   * Value of the revoked status in the security coverage field.
   *
   * @const string
   */
  protected const REVOKED_STATUS = 'revoked';

  /**
   * This is what Drupal.org understands as "Covered" modules.
   *
   * @var array
   */
  private const COVERED_VALUES = ['covered'];

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly ClientInterface $httpClient,
    protected readonly CacheBackendInterface $cacheBin,
    protected readonly TimeInterface $time,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected ?LoggerInterface $logger = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    // @phpstan-ignore-next-line
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ClientInterface::class),
      $container->get('cache.project_browser'),
      $container->get(TimeInterface::class),
      $container->get(ModuleHandlerInterface::class),
      $container->get(LoggerChannelFactoryInterface::class)->get('project_browser'),
    );
  }

  /**
   * Performs a request to the API and returns the results.
   *
   * @param string $url
   *   URL to query.
   * @param array $query_params
   *   Params to pass to the query.
   * @param bool $all_data
   *   Fetch all data from all pages (defaults to FALSE).
   *
   * @return array
   *   Results from the query.
   */
  protected function fetchData(string $url, array $query_params = [], bool $all_data = FALSE): array {
    // Failsafe to avoid timeouts or memory issues.
    // 50 results per page x 10 iterations = 500 results.
    $iteration_limit = 10;
    $result = [
      'code' => NULL,
      'data' => NULL,
      'message' => '',
    ];
    $params = [];
    try {
      if (!empty($query_params)) {
        $params = [
          'query' => $query_params,
        ];
      }
      $response = $this->httpClient->request('GET', $url, $params);
      $response_data = Json::decode($response->getBody()->getContents());

      $result['code'] = $response->getStatusCode();
      $result['data'] = $response_data['data'];
      $result['meta'] = $response_data['meta'] ?? NULL;
      if (!empty($response_data['included'])) {
        $result['included'] = $response_data['included'];
      }
      if ($all_data) {
        // Start querying the "next" pages until there are no more of them, or
        // we reach the iteration max limit.
        $iterations = 0;
        while (!empty($response_data['links']['next']) && $iterations < $iteration_limit) {
          // Params are already in the URL for the "next" request.
          $url = $response_data['links']['next']['href'];
          $response = $this->httpClient->request('GET', $url);
          $response_data = Json::decode($response->getBody()->getContents());

          $result['data'] = array_merge($result['data'], $response_data['data']);
          if (!empty($response_data['included']) && !empty($result['included'])) {
            $result['included'] = array_merge($result['included'], $response_data['included']);
          }
          $iterations++;
        }

        if ($iterations >= $iteration_limit) {
          $result['message'] = $this->t('Max limit reached: Result data has been truncated to %limit records.', [
            '%limit' => count($result['data']),
          ]);
        }
      }
    }
    catch (\Throwable $exception) {
      $error_code = (int) $exception->getCode();
      $this->logger?->error('Error code: @error_code.<br>Message: @error_message.<br>You can report the issue <a href="@report_issue">in the Drupal.org issue queue</a>.', [
        '@error_code' => $error_code,
        '@error_message' => $exception->getMessage(),
        '@report_issue' => 'https://www.drupal.org/node/add/project-issue/drupalorg',
      ]);
      $reason = $this->t('An error occurred while fetching data from Drupal.org.');
      if ($error_code >= 400 && $error_code < 500) {
        $reason = ($error_code === 403) ?
        $this->t('The request made to Drupal.org is likely invalid or might have been blocked. Ensure you are running the latest version of Project Browser') :
        $this->t('The request made to Drupal.org is likely invalid. Ensure you are running the latest version of Project Browser');
      }
      if ($this->moduleHandler->moduleExists('dblog')) {
        $dblog_url = Url::fromRoute('dblog.overview')->toString();
        $result['message'] = $this->t('@reason. See the <a href="@dblog_url">error log</a> for details. While this error persists, you can <a href="@drupalorg_catalog">browse modules on Drupal.org</a>.', [
          '@dblog_url' => $dblog_url,
          '@reason' => $reason,
          '@drupalorg_catalog' => 'https://www.drupal.org/project/project_module',
        ]);
      }
      else {
        $result['message'] = $this->t('@reason. See the error log for details. While this error persists, you can <a href="@drupalorg_catalog">browse modules on Drupal.org</a>.', [
          '@reason' => $reason,
          '@drupalorg_catalog' => 'https://www.drupal.org/project/project_module',
        ]);
      }
      $result['code'] = $error_code;
    }

    return $result;
  }

  /**
   * Processes the included data returned by jsonapi and map by type.
   *
   * @param array $included
   *   Data from jsonapi with all included information.
   *
   * @return array
   *   Mapped array keyed by type and id.
   */
  protected function mapIncludedData(array $included): array {
    $mapped_array = [];
    foreach ($included as $item) {
      $mapped_array[$item['type']][$item['id']] = $item['attributes'];
    }

    return $mapped_array;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    $filters = [
      'search' => new TextFilter('', $this->t('Search')),
    ];

    $filters['categories'] = new MultipleChoiceFilter(
      $this->getCategories(),
      [],
      $this->t('Categories'),
    );
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
  protected function getCategories(): array {
    $cid = $this->getPluginId() . ':categories';
    $cached = $this->cacheBin->get($cid);
    if ($cached) {
      return $cached->data;
    }

    $endpoint = self::JSONAPI_ENDPOINT . '/taxonomy_term/module_categories';
    $query_params = [
      'sort' => 'name',
      'filter[status]' => 1,
      'fields[taxonomy_term--module_categories]' => 'name',
    ];
    $result = $this->fetchData($endpoint, $query_params, TRUE);

    $categories = [];
    if ($result['code'] == Response::HTTP_OK && !empty($result['data'])) {
      foreach ($result['data'] as $item) {
        $categories[$item['id']] = $item['attributes']['name'];
      }
    }
    $this->cacheBin->set($cid, $categories);
    return $categories;
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
  private function bodyRelativeToAbsoluteUrls(array $body, string $base_url = self::DRUPAL_ORG_ENDPOINT): array {
    if (empty($body['value'])) {
      $body['value'] = $body['summary'] ?? '';
    }
    $body['value'] = Html::transformRootRelativeUrlsToAbsolute($body['value'], $base_url);

    return $body;
  }

  /**
   * Maps a given field to the allowed fields to sort results.
   *
   * @param string $field
   *   Name of the field.
   *
   * @return null|string
   *   Mapped field name or NULL if not available.
   *
   * @see ProjectBrowserSourceBase::getSortOptions()
   */
  private function mapSortField(string $field): ?string {
    $map = [
      'usage_total' => 'active_installs_total',
      'created' => 'created',
      // 'best_match' would be the default sort.
      'best_match' => NULL,
      'a_z' => 'title',
      'z_a' => 'title',
    ];

    return $map[$field] ?? NULL;
  }

  /**
   * Maps a given field to the direction within the allowed values.
   *
   * @param string $field
   *   Name of the field.
   *
   * @return null|string
   *   Mapped direction or NULL if not available.
   *
   * @see ProjectBrowserSourceBase::getSortOptions()
   */
  private function mapSortDirection(string $field): ?string {
    $map = [
      'usage_total' => 'DESC',
      'created' => 'DESC',
      // 'best_match' would be the default sort.
      'best_match' => NULL,
      'a_z' => 'ASC',
      'z_a' => 'DESC',
    ];

    return $map[$field] ?? NULL;
  }

  /**
   * Translates a numeric semver version into a number in the expected format.
   *
   * It will do three blocks of three digits with padding zeros to the left.
   * ie:
   * - 9.3.6 will translate to 9003006.
   * - 10.4.12 will translate to 10004012.
   *
   * @param string $version
   *   Semver version to check. It should follow X.Y.Z format.
   *
   * @return int
   *   Numeric representation of the given version.
   */
  private function getNumericSemverVersion(string $version): int {
    $version_object = ExtensionVersion::createFromVersionString($version);
    if ($extra = $version_object->getVersionExtra()) {
      $version = str_replace("-$extra", '', $version);
    }
    $minor_version = $version_object->getMinorVersion() ?? '0';
    $patch_version = explode('.', $version)[2] ?? '0';

    return (int) (
      $version_object->getMajorVersion() .
      str_pad($minor_version, 3, '0', STR_PAD_LEFT) .
      str_pad($patch_version, 3, '0', STR_PAD_LEFT)
    );
  }

  /**
   * Build the right query based on the field name and the values given.
   *
   * @param string $field_name
   *   Vocabulary to query.
   * @param string $values
   *   A Comma-separated list of values to check.
   * @param array $query_params
   *   Query params that will be passed to the request.
   * @param bool $negate
   *   Make the query a 'NOT IN' instead of an 'IN'.
   *
   * @return array
   *   New list of params containing the new filters.
   */
  protected function addQueryParamsMultivalue(string $field_name, string $values, array $query_params, bool $negate = FALSE): array {
    $values = explode(',', $values);
    $operator = ($negate) ? 'NOT IN' : 'IN';
    $field = ($negate) ? 'n_' . $field_name : $field_name;
    $index = 0;
    foreach ($values as $value) {
      $value = trim($value);
      $query_params['filter[' . $field . '][value][' . $index . ']'] = $value;
      $index++;
    }
    $query_params['filter[' . $field . '][operator]'] = $operator;
    $query_params['filter[' . $field . '][path]'] = $field_name;

    return $query_params;
  }

  /**
   * Add the core version filters to the query.
   *
   * @param array $query_params
   *   Query params that will be passed to the request.
   *
   * @return array
   *   New list of params containing the new filters.
   */
  protected function addCoreVersionCheck(array $query_params): array {
    $current_drupal_version = $this->getNumericSemverVersion(\Drupal::VERSION);
    if ($current_drupal_version) {
      $field = 'core_semver_minimum';
      $query_params['filter[' . $field . '][value]'] = $current_drupal_version;
      $query_params['filter[' . $field . '][operator]'] = '<=';
      $query_params['filter[' . $field . '][path]'] = $field;

      $field = 'core_semver_maximum';
      $query_params['filter[' . $field . '][value]'] = $current_drupal_version;
      $query_params['filter[' . $field . '][operator]'] = '>=';
      $query_params['filter[' . $field . '][path]'] = $field;
    }

    return $query_params;
  }

  /**
   * Returns the filter values from the www.drupal.org endpoint.
   *
   * @return array
   *   Filter values by taxonomy.
   */
  protected function filterValues(): array {
    $values = [];
    $url = self::DRUPAL_ORG_ENDPOINT . '/drupalorg-api/project-browser-filters';
    $url .= '?drupal_version=' . \Drupal::VERSION;
    $cid = $this->getPluginId() . ':filter_values';
    $cached = $this->cacheBin->get($cid);
    if ($cached) {
      return $cached->data;
    }
    try {
      $response = $this->httpClient->request('GET', $url);
      $values = Json::decode($response->getBody()->getContents());
      $this->cacheBin->set($cid, $values, $this->time->getRequestTime() + 3600);
    }
    catch (\Throwable $exception) {
      $this->logger?->error($exception->getMessage());
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function convertQueryOptions(array $query = []): array {
    $filter_values = $this->filterValues();
    $active_values = $filter_values['active'] ?? [];
    $maintained_values = $filter_values['maintained'] ?? [];

    // Sort options.
    $sort = NULL;
    if (!empty($query['sort'])) {
      $sort = $this->mapSortDirection($query['sort']);
      if (in_array($sort, ['ASC', 'DESC'])) {
        $sort = ($sort == 'DESC') ? '-' : '';
        $sort_field = $this->mapSortField($query['sort']);
        $sort = ($sort_field) ? $sort . $sort_field : FALSE;
      }
    }
    $query['sort'] = $sort;

    // Maintenance options.
    $maintenance = NULL;
    if (!empty($query['maintenance_status'])) {
      $maintenance = implode(',', $maintained_values);
    }
    $query['maintenance_status'] = $maintenance;

    // Development options.
    $development = NULL;
    if (!empty($query['development_status'])) {
      $development = implode(',', $active_values);
    }
    $query['development_status'] = $development;

    // Security options.
    $security = NULL;
    if (!empty($query['security_advisory_coverage'])) {
      $security = implode(',', self::COVERED_VALUES);
    }
    $query['security_advisory_coverage'] = $security;

    // Defaults in case none is given.
    $query['page'] = $query['page'] ?? 0;
    $query['limit'] = $query['limit'] ?? 12;

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    return [
      'best_match' => $this->t('Most relevant'),
      'created' => $this->t('Newest first'),
    ];
  }

  /**
   * Maps JSON:API project data to a Project Browser Project object.
   *
   * @param array $project
   *   The JSON:API project data.
   * @param array|null $related
   *   An associative array containing related entities referenced in the
   *   project data, indexed by type and ID.
   *
   * @return \Drupal\project_browser\ProjectBrowser\Project
   *   A single project.
   */
  protected function getMappedProjectData(array $project, ?array $related): Project {
    $filter_values = $this->filterValues();
    $current_drupal_version = $this->getNumericSemverVersion(\Drupal::VERSION);
    $maintained_values = $filter_values['maintained'] ?? [];
    // Map any properties from jsonapi format to the simplified record
    // format used by Project Browser.
    $machine_name = $project['attributes']['field_project_machine_name'];

    $maintenance_status = $project['relationships']['field_maintenance_status']['data'] ?? [];
    if (is_array($maintenance_status)) {
      $maintenance_status = $maintenance_status['id'];
    }

    $module_categories = $project['relationships']['field_module_categories']['data'] ?? NULL;
    if (is_array($module_categories) && is_array($related)) {
      $categories = [];
      foreach ($module_categories as $module_category) {
        $categories[] = [
          'id' => $module_category['id'],
          'name' => $related[$module_category['type']][$module_category['id']]['name'],
        ];
      }
      $module_categories = $categories;
    }

    $project_images = $project['relationships']['field_project_images']['data'] ?? NULL;
    if (is_array($project_images) && is_array($related)) {
      $images = [];
      foreach ($project_images as $image) {
        if ($image['id'] !== 'missing') {
          $uri = self::DRUPAL_ORG_ENDPOINT . $related[$image['type']][$image['id']]['uri']['url'];
          // Adapt the path as we are querying via www.drupal.org.
          $uri = str_replace(self::DRUPAL_ORG_ENDPOINT . '/assets/', self::DRUPAL_ORG_ENDPOINT . '/files/', $uri);
          $images[] = [
            'file' => Url::fromUri($uri),
            'alt' => $image['meta']['alt'] ?? '',
          ];
        }
      }
      $project_images = $images;
    }

    $project_usage = $project['attributes']['field_active_installs'];
    $project_usage_total = 0;
    if ($project_usage) {
      $project_usage = Json::decode($project_usage);
      foreach ($project_usage as $value) {
        $project_usage_total += (int) $value;
      }
    }

    $is_compatible = FALSE;
    $semver_minimum = (int) $project['attributes']['field_core_semver_minimum'];
    $semver_maximum = (int) $project['attributes']['field_core_semver_maximum'];
    if (($semver_minimum <= $current_drupal_version) && ($semver_maximum >= $current_drupal_version)) {
      $is_compatible = TRUE;
    }

    $logo = NULL;
    if (!empty($project['attributes']['field_logo_url'])) {
      $logo = Url::fromUri($project['attributes']['field_logo_url']['uri']);
    }

    $body = $this->bodyRelativeToAbsoluteUrls(
      $project['attributes']['body'] ?? ['summary' => '', 'value' => ''], 'https://www.drupal.org');

    return new Project(
      logo: $logo ?? NULL,
      isCompatible: $is_compatible,
      machineName: $machine_name,
      body: $body,
      title: $project['attributes']['title'],
      packageName: $project['attributes']['field_composer_namespace'] ?? 'drupal/' . $machine_name,
      projectUsageTotal: $project_usage_total,
      isCovered: in_array($project['attributes']['field_security_advisory_coverage'], self::COVERED_VALUES, TRUE),
      isMaintained: in_array($maintenance_status, $maintained_values, TRUE),
      url: Url::fromUri('https://www.drupal.org/project/' . $machine_name),
      categories: $module_categories ?? [],
      images: $project_images ?? [],
    );
  }

}
