<?php

namespace Drupal\project_browser\Plugin\ProjectBrowserSource;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\MultipleChoiceFilter;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A source that lists Drupal core modules.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[ProjectBrowserSource(
  id: 'drupal_core',
  label: new TranslatableMarkup('Core modules'),
  description: new TranslatableMarkup('Modules included in Drupal core'),
  local_task: [
    'title' => new TranslatableMarkup('Core modules'),
  ],
)]
final class DrupalCore extends ProjectBrowserSourceBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly CacheBackendInterface $cacheBin,
    private readonly ModuleExtensionList $moduleList,
    private readonly string|false|null $installProfile,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $install_profile = $container->getParameter('install_profile');
    assert(is_string($install_profile) || $install_profile === FALSE || is_null($install_profile));

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.project_browser'),
      $container->get(ModuleExtensionList::class),
      $install_profile,
    );
  }

  /**
   * Filters module extension list for core modules.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The array containing core modules, keyed by module machine name.
   */
  private function getCoreModules(): array {
    $modules = array_filter(
      $this->moduleList->reset()->getList(),
      fn (Extension $module): bool => $module->origin === 'core',
    );
    // Don't include the current install profile, if there is one.
    if ($this->installProfile) {
      unset($modules[$this->installProfile]);
    }
    // If we're including test modules, no further filtering is needed.
    if (Settings::get('extension_discovery_scan_tests') || drupal_valid_test_ua()) {
      return $modules;
    }
    // Only return non-hidden modules that aren't in the `Testing` package.
    return array_filter(
      $modules,
      fn (Extension $module): bool => empty($module->info['hidden']) && $module->info['package'] !== 'Testing',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    $filters = [
      'search' => new TextFilter('', $this->t('Search')),
    ];

    $categories = [];
    foreach ($this->getCoreModules() as $module) {
      $package = $module->info['package'];
      $categories[$package] = $package;
    }
    asort($categories, SORT_NATURAL);
    $filters['categories'] = new MultipleChoiceFilter($categories, [], $this->t('Categories'));

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []) : ProjectsResultsPage {
    $projects = $this->getProjectData();

    // Filter by project machine name.
    if (!empty($query['machine_name'])) {
      $projects = array_filter($projects, fn(Project $project): bool => $project->machineName === $query['machine_name']);
    }

    // Filter by coverage.
    if (!empty($query['security_advisory_coverage'])) {
      $projects = array_filter($projects, fn(Project $project): bool => $project->isCovered ?? FALSE);
    }

    // Filter by categories.
    if (!empty($query['categories'])) {
      $projects = array_filter($projects, fn(Project $project): bool => !empty(array_intersect(array_column($project->categories, 'id'), explode(',', $query['categories']))));
    }

    // Filter by search text.
    if (!empty($query['search'])) {
      $projects = array_filter($projects, fn(Project $project): bool => stripos($project->title, $query['search']) !== FALSE);
    }

    // Filter by sorting criterion.
    if (!empty($query['sort'])) {
      $sort = $query['sort'];
      switch ($sort) {
        case 'a_z':
          usort($projects, fn($x, $y) => $x->title <=> $y->title);
          break;

        case 'z_a':
          usort($projects, fn($x, $y) => $y->title <=> $x->title);
          break;
      }
    }
    $project_count = count($projects);
    if (!empty($query['page']) && !empty($query['limit'])) {
      $projects = array_chunk($projects, $query['limit'])[$query['page']] ?? [];
    }
    if (array_key_exists('order', $this->configuration)) {
      SortHelper::sortInDefinedOrder($projects, $this->configuration['order']);
    }
    return $this->createResultsPage($projects, $project_count);
  }

  /**
   * Gets the project data from cache if available, or builds it if not.
   *
   * @return \Drupal\project_browser\ProjectBrowser\Project[]
   *   Array of projects.
   */
  private function getProjectData(): array {
    $stored_projects = $this->cacheBin->get('DrupalCore:projects');
    if ($stored_projects) {
      return $stored_projects->data;
    }

    $returned_list = [];
    foreach ($this->getCoreModules() as $module_name => $module) {
      // Dummy data is used for the fields that are unavailable for core
      // modules.
      $returned_list[] = new Project(
        logo: Url::fromUri('base:/core/misc/logo/drupal-logo.svg'),
        // All core projects are considered compatible.
        isCompatible: TRUE,
        isMaintained: TRUE,
        isCovered: $module->info['package'] !== 'Core (Experimental)',
        machineName: $module_name,
        body: [
          'summary' => $module->info['description'],
          'value' => $module->info['description'],
        ],
        title: $module->info['name'],
        packageName: 'drupal/core',
        categories: [
          [
            'id' => $module->info['package'],
            'name' => $module->info['package'],
          ],
        ],
        id: $module_name,
      );
    }

    $this->cacheBin->set('DrupalCore:projects', $returned_list);
    return $returned_list;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    return [
      'a_z' => $this->t('A-Z'),
      'z_a' => $this->t('Z-A'),
    ];
  }

}
