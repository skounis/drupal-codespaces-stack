<?php

namespace Drupal\project_browser_source_example\Plugin\ProjectBrowserSource;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\MultipleChoiceFilter;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Project Browser Source Plugin example code.
 */
#[ProjectBrowserSource(
  id: 'project_browser_source_example',
  label: new TranslatableMarkup('Example source'),
  description: new TranslatableMarkup('Example source plugin for Project Browser.'),
)]
final class ProjectBrowserSourceExample extends ProjectBrowserSourceBase {

  /**
   * Constructor for example plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request from the browser.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RequestStack $requestStack,
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
      $container->get(RequestStack::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    // Each of the following steps could be done in a separate function. We are
    // including them here for simplicity.
    // --
    // Step 1: Interpret the $query made and adapt it to your needs so you can
    // filter and sort the data accordingly.
    // @see \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
    $categories_filter = explode(',', $query['categories'] ?? '');
    $machine_name_filter = $query['machine_name'] ?? '';

    // Step 2: Get the projects from wherever your source is and create Project
    // Browser "Project" objects for the final result-set. This could be your
    // own REST endpoint, GraphQL, a Google Spreadsheet, anything! Refer to
    // other plugins if you want more examples. The $query should be taken into
    // account when filtering, but adapted to the way that you obtain the data.
    $request = $this->requestStack->getCurrentRequest();
    $projects_from_source = [
      // The source data can use any keys, we will adapt it later.
      [
        'identifier' => 'p1',
        'unique_name' => 'project_1',
        'label' => 'Project 1',
        'short_description' => 'Quick summary to show in the cards.',
        'long_description' => 'Extended project information to show in the detail page',
        'logo' => $request?->getSchemeAndHttpHost() . '/core/misc/logo/drupal-logo.svg',
        'created_at' => strtotime('1 year ago'),
        'updated_at' => strtotime('1 month ago'),
        'categories' => ['cat_1:Category 1'],
        'composer_namespace' => 'my-awesome-namespace/project_1',
      ],
    ];

    // Very basic filtering for this example based on the query made.
    // The filtering itself is done at the source, it can happen before or
    // after, it's up to your source and how it works.
    if (in_array('cat_2', $categories_filter) && !in_array('cat_1', $categories_filter)) {
      array_pop($projects_from_source);
    }
    if (!empty($machine_name_filter) && $machine_name_filter !== 'project_1') {
      array_pop($projects_from_source);
    }

    // Step 3: You MUST set each of the following properties for every
    // project in your result-set. Here is an example of a Project object
    // fully populated.
    $projects = [];
    foreach ($projects_from_source as $project_from_source) {
      $logo = Url::fromUri($project_from_source['logo']);

      // Empty array if there are no categories.
      $categories = [];
      foreach ($project_from_source['categories'] as $category) {
        [$id, $name] = explode(':', $category);
        $categories[] = [
          'id' => $id,
          'name' => $name,
        ];
      }

      $projects[] = new Project(
        logo: $logo,
        // Maybe the source won't have all fields, but we still need to
        // populate the values of all the properties.
        isCompatible: TRUE,
        isMaintained: TRUE,
        isCovered: TRUE,
        machineName: $project_from_source['unique_name'],
        body: [
          'summary' => $project_from_source['short_description'],
          'value' => $project_from_source['long_description'],
        ],
        title: $project_from_source['label'],
        packageName: $project_from_source['composer_namespace'],
        categories: $categories,
        // Images: Array of images, each of which is an array with two elements:
        // `file`, which is a \Drupal\Core\Url object pointing to the image,
        // and `alt`, which is the alt text.
        images: [],
      );
      $projects[] = new Project(
        logo: $logo,
        // Maybe the source won't have all fields, but we still need to
        // populate the values of all the properties.
        isCompatible: TRUE,
        isMaintained: TRUE,
        isCovered: TRUE,
        machineName: $project_from_source['unique_name'] . '2',
        body: [
          'summary' => $project_from_source['short_description'] . ' (different commands)',
          'value' => $project_from_source['long_description'] . ' (different commands)',
        ],
        title: 'A project with different commands',
        packageName: $project_from_source['composer_namespace'],
        categories: $categories,
        // Images: Array of images, each of which is an array with two elements:
        // `file`, which is a \Drupal\Core\Url object pointing to the image,
        // and `alt`, which is the alt text.
        images: [],
      );
    }

    // Return one page of results. The first parameter is the total number of
    // results for the set, as filtered by $query.
    return $this->createResultsPage($projects);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    $filters = [
      'search' => new TextFilter('', $this->t('Search')),
    ];

    $categories = [
      'cat_1' => 'Category 1',
      'cat_2' => 'Category 2',
    ];
    $filters['categories'] = new MultipleChoiceFilter($categories, [], $this->t('Categories'));

    return $filters;
  }

}
