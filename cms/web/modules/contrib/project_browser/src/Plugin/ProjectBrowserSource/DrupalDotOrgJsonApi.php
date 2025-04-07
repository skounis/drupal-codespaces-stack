<?php

namespace Drupal\project_browser\Plugin\ProjectBrowserSource;

use Drupal\project_browser\Plugin\DrupalDotOrgSourceBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drupal.org JSON:API endpoint.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[ProjectBrowserSource(
  id: 'drupalorg_jsonapi',
  label: new TranslatableMarkup('Contrib modules'),
  description: new TranslatableMarkup('Modules on Drupal.org queried via the JSON:API endpoint'),
  local_task: [
    'title' => new TranslatableMarkup('Contrib modules'),
  ],
)]
final class DrupalDotOrgJsonApi extends DrupalDotOrgSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    $filter_values = $this->filterValues();
    if (!empty($filter_values['drupal_version']) && $filter_values['drupal_version']['supported'] === FALSE) {
      $error_message = $filter_values['drupal_version']['message'] ?? $this->t('The current version of Drupal is not supported in the Drupal.org endpoint.');
      return $this->createResultsPage([], 0, $error_message);
    }

    $api_response = $this->fetchProjects($query);
    if ($api_response['code'] !== Response::HTTP_OK) {
      $error_message = $api_response['message'] ?? $this->t('Error querying data.');
      return $this->createResultsPage([], 0, $error_message);
    }

    $returned_list = [];
    if (!empty($api_response['list'])) {
      $related = !empty($api_response['related']) ? $api_response['related'] : NULL;
      foreach ($api_response['list'] as $project) {
        $returned_list[] = $this->getMappedProjectData($project, $related);
      }
    }

    if (array_key_exists('order', $this->configuration)) {
      SortHelper::sortInDefinedOrder($returned_list, $this->configuration['order']);
    }
    return $this->createResultsPage($returned_list, $api_response['total_results'] ?? 0);
  }

  /**
   * Fetches the projects from the jsonapi backend.
   *
   * @param array $query
   *   Query parameters.
   *
   * @return array
   *   Array containing the results and the total number of records.
   */
  private function fetchProjects(array $query): array {
    $query = $this->convertQueryOptions($query);

    $query_params = [
      'filter[status]' => 1,
      // For now, we only want full "module" projects.
      'filter[type]' => 'project_module',
      'filter[project_type]' => 'full',
      'page[limit]' => $query['limit'],
      'page[offset]' => $query['limit'] * $query['page'],
      'include' => 'field_module_categories,field_maintenance_status,field_development_status,uid,field_project_images',
    ];

    if (!is_null($query['sort'])) {
      $query_params['sort'] = $query['sort'];
    }

    if (!empty($query['search'])) {
      $query_params['filter[fulltext]'] = $query['search'];
    }

    if (!empty($query['machine_name'])) {
      $query_params = $this->addQueryParamsMultivalue('machine_name', $query['machine_name'], $query_params);
    }

    // For now, we only want compatible projects.
    $query_params = $this->addCoreVersionCheck($query_params);

    if ($query['categories']) {
      $query_params = $this->addQueryParamsMultivalue('module_categories_uuid', $query['categories'], $query_params);
    }
    if ($query['maintenance_status']) {
      $query_params = $this->addQueryParamsMultivalue('maintenance_status_uuid', $query['maintenance_status'], $query_params);
    }
    if ($query['development_status']) {
      $query_params = $this->addQueryParamsMultivalue('development_status_uuid', $query['development_status'], $query_params);
    }
    if ($query['security_advisory_coverage']) {
      $query_params = $this->addQueryParamsMultivalue('security_coverage', $query['security_advisory_coverage'], $query_params);
    }
    // We will never want 'revoked' projects.
    $query_params = $this->addQueryParamsMultivalue('security_coverage', self::REVOKED_STATUS, $query_params, TRUE);

    $result = $this->fetchData(self::JSONAPI_MODULES_ENDPOINT, $query_params);
    $return = [
      'code' => $result['code'],
      'total_results' => 0,
      'list' => [],
    ];
    if ($result['code'] === Response::HTTP_OK && !empty($result['data'])) {
      // Related data referenced by any possible data entry.
      $included = !empty($result['included']) ? $this->mapIncludedData($result['included']) : FALSE;

      $return['related'] = $included;
      $return['total_results'] = $result['meta']['count'] ?? count($result['data']);
      $return['list'] = $result['data'];
    }

    if ($result['code'] !== Response::HTTP_OK) {
      $return['message'] = $result['message'] ?? $this->t('Error when fetching the data.');
    }

    return $return;
  }

}
