<?php

namespace Drupal\project_browser\Element;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a render element to display a project browser.
 *
 * Properties:
 * - #source: An instance of a Project Browser source plugin that implements
 *   \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface.
 * - #id: (optional) An internal identifier for this Project Browser instance.
 *   This is never displayed to the user and will be randomly generated if not
 *   provided. There is usually no reason to specify it explicitly. It is not a
 *   CSS ID, and cannot be used for styling.
 * - #sort_options: (optional) The sort options to offer to the user. If given,
 *   must be an associative array whose keys exist in the sort options returned
 *   from the source plugin's ::getSortOptions() method. The values are the
 *   human-readable names for the sort options, and will be shown to the user.
 *   The human-readable names can be any arbitrary string. This property
 *   defaults to the sort options as defined by the source plugin.
 * - #sort_by: (optional) The default sort criterion. Must be one of the keys of
 *   the array returned by the source plugin's ::getSortOptions() method. If not
 *   specified, defaults to the first defined sort criterion.
 * - #paginate: (optional) Whether or not to enable pagination. Boolean,
 *   defaults to TRUE. If pagination is disabled, only the first page of
 *   projects will be displayed, and the user will not be able to advance to a
 *   different page or change the page size.
 * - #page_sizes: (optional) An array of options to present the user for them to
 *   choose how many projects to display on each page, if pagination is enabled.
 *   Must be an array of numbers that are greater than zero. Does not need to be
 *   in any particular order. Defaults to 12, 24, 36, and 48.
 * - #filters: (optional) Associative array of filters where keys are filter
 *   machine names, and values are their default values. If provided, only
 *   these filters will be displayed, and their default values will be
 *   set accordingly.
 *
 *  Usage example:
 *
 * @code
 *  $source = \Drupal::service(ProjectBrowserSourceManager::class)
 *    ->createInstance('drupalorg_jsonapi');
 *
 *  $build['projects'] = [
 *    '#type' => 'project_browser',
 *    '#source' => $source,
 *    '#sort_options' => [
 *      'a_z' => t('Alphabetical'),
 *      'popularity' => t('Most liked'),
 *    ],
 *    '#sort_by' => 'a_z',
 *    '#paginate' => FALSE,
 *    '#page_sizes' => [5, 10, 25],
 *    '#filters' => [
 *      'development_status' => TRUE,
 *      'categories' => [123, 456],
 *    ],
 *  ];
 * @endcode
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[RenderElement('project_browser')]
final class ProjectBrowser extends RenderElementBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UuidInterface $uuid,
    private readonly CurrentPathStack $currentPath,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ModuleHandlerInterface::class),
      $container->get(ConfigFactoryInterface::class),
      $container->get(UuidInterface::class),
      $container->get(CurrentPathStack::class),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#theme' => 'project_browser_main_app',
      '#attached' => [
        'library' => [
          'project_browser/app',
        ],
        'drupalSettings' => [
          'project_browser' => [],
        ],
      ],
      '#pre_render' => [
        [$this, 'attachProjectBrowserSettings'],
      ],
      '#page_sizes' => [12, 24, 36, 48],
    ];
  }

  /**
   * Prepares a render element for Project Browser.
   *
   * @param array $element
   *   A render element array.
   *
   * @return array
   *   The render element array.
   */
  public function attachProjectBrowserSettings(array $element): array {
    assert($element['#source'] instanceof ProjectBrowserSourceInterface);
    $source = $element['#source'];

    $element['#id'] ??= $this->uuid->generate();

    $sort_options = $source->getSortOptions();
    // If the element specifies sort options, ensure that they are all defined
    // by the source plugin.
    if (isset($element['#sort_options'])) {
      $unknown_sort_options = array_diff_key($element['#sort_options'], $sort_options);
      if ($unknown_sort_options) {
        throw new \InvalidArgumentException("Unknown sort option(s): " . implode(', ', array_keys($unknown_sort_options)));
      }

      $sort_options = $element['#sort_options'];
      if (empty($sort_options)) {
        throw new \InvalidArgumentException('At least one sort option must be defined.');
      }
    }

    $sort_by = $element['#sort_by'] ?? key($sort_options);
    assert(
      array_key_exists($sort_by, $sort_options),
      new \InvalidArgumentException("'$sort_by' is not a valid sort criterion."),
    );

    $page_sizes = array_map('intval', $element['#page_sizes']);
    assert(
      count($page_sizes) > 0 &&
      Inspector::assertAll(fn (int $value): bool => $value > 0, $page_sizes),
      new \InvalidArgumentException('#page_sizes must be an array of integers greater than zero.'),
    );
    // This sort will re-key the array.
    sort($page_sizes);

    // If the element overrides the filters, ensure all of them are defined by
    // the source plugin, and allow the element to override the default values.
    $filters = $source->getFilterDefinitions();
    if (isset($element['#filters'])) {
      assert(is_array($element['#filters']));

      $invalid_filters = array_diff_key($element['#filters'], $filters);
      if ($invalid_filters) {
        throw new \InvalidArgumentException('Unknown filter(s): ' . implode(', ', array_keys($invalid_filters)));
      }

      $filters = array_intersect_key($filters, $element['#filters']);
      foreach ($element['#filters'] as $name => $default_value) {
        $filters[$name]->setValue($default_value);
      }
    }

    $global_settings = $this->configFactory->get('project_browser.admin_settings');

    $element['#attached']['drupalSettings']['project_browser'] = [
      'module_path' => $this->moduleHandler->getModule('project_browser')->getPath(),
      'default_plugin_id' => $source->getPluginId(),
      'package_manager' => $global_settings->get('allow_ui_install') && $this->moduleHandler->moduleExists('package_manager'),
      'max_selections' => $global_settings->get('max_selections') ?? NULL,
      'current_path' => '/' . $this->currentPath->getPath(),
      'instances' => [
        $element['#id'] => [
          'source' => $source->getPluginId(),
          'name' => $source->getPluginDefinition()['label'],
          // Cast these to objects so that they will still be encoded as objects
          // even if they are empty arrays.
          'filters' => (object) $filters,
          'sorts' => (object) $sort_options,
          'sortBy' => $sort_by,
          'paginate' => $element['#paginate'] ?? TRUE,
          'pageSizes' => $page_sizes,
        ],
      ],
    ];
    return $element;
  }

}
