<?php

namespace Drupal\simple_sitemap_views\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Exception\SkipElementException;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGeneratorBase;
use Drupal\simple_sitemap\Settings;
use Drupal\simple_sitemap_views\SimpleSitemapViews;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views URL generator plugin.
 *
 * @UrlGenerator(
 *   id = "views",
 *   label = @Translation("Views URL generator"),
 *   description = @Translation("Generates URLs for views."),
 * )
 */
class ViewsUrlGenerator extends EntityUrlGeneratorBase {

  /**
   * Views sitemap data.
   *
   * @var \Drupal\simple_sitemap_views\SimpleSitemapViews
   */
  protected $sitemapViews;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * ViewsUrlGenerator constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   The simple_sitemap.logger service.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   The simple_sitemap.entity_helper service.
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Logger $logger,
    Settings $settings,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityHelper $entity_helper,
    SimpleSitemapViews $sitemap_views,
    RouteProviderInterface $route_provider,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $logger,
      $settings,
      $language_manager,
      $entity_type_manager,
      $entity_helper
    );
    $this->sitemapViews = $sitemap_views;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('simple_sitemap.views'),
      $container->get('router.route_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSets(): array {
    $data_sets = [];

    // Get data sets.
    foreach ($this->sitemapViews->getIndexableViews() as $view) {
      $settings = $this->sitemapViews->getSitemapSettings($view, $this->sitemap->id());

      if (empty($settings)) {
        $view->destroy();
        continue;
      }

      $base_data_set = [
        'view_id' => $view->id(),
        'display_id' => $view->current_display,
      ];

      $extender = $this->sitemapViews->getDisplayExtender($view);

      // View path without arguments.
      if (!$extender->hasRequiredArguments()) {
        $data_sets[] = $base_data_set + ['arguments' => NULL];
      }

      // Process indexed arguments.
      if ($args_ids = $this->sitemapViews->getIndexableArguments($view, $this->sitemap->id())) {
        $args_ids = $this->sitemapViews->getArgumentsStringVariations($args_ids);

        // Form the condition according to the variants of the
        // indexable arguments.
        $condition = Database::getConnection()->condition('AND');
        $condition->condition('view_id', $view->id());
        $condition->condition('display_id', $view->current_display);
        $condition->condition('arguments_ids', $args_ids, 'IN');

        // Get the arguments values from the index.
        $max_links = is_numeric($settings['max_links']) ? $settings['max_links'] : NULL;
        $indexed_arguments = $this->sitemapViews->getArgumentsFromIndex($condition, $max_links, TRUE);

        // Add the arguments values for processing.
        foreach ($indexed_arguments as $index_id => $arguments_info) {
          $data_sets[] = $base_data_set + [
            'index_id' => $index_id,
            'arguments' => $arguments_info['arguments'],
          ];
        }
      }

      // Destroy a view instance.
      $view->destroy();
    }

    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($data_set): array {
    // Get information from data set.
    $view_id = $data_set['view_id'];
    $display_id = $data_set['display_id'];
    $args = $data_set['arguments'];

    try {
      // Trying to get an instance of the view.
      $view = Views::getView($view_id);
      if ($view === NULL) {
        throw new \UnexpectedValueException('Failed to get an instance of the view.');
      }

      // Trying to set the view display.
      $view->initDisplay();
      if (!$view->displayHandlers->has($display_id) || !$view->setDisplay($display_id)) {
        throw new \UnexpectedValueException('Failed to set the view display.');
      }

      // Trying to get the sitemap settings.
      $settings = $this->sitemapViews->getSitemapSettings($view, $this->sitemap->id());
      if (empty($settings)) {
        throw new \UnexpectedValueException('Failed to get the sitemap settings.');
      }

      // Trying to get the view URL.
      $url = $view->getUrl($args);
      $url->setAbsolute();

      if (is_array($args)) {
        $params = array_merge([$view_id, $display_id], $args);
        $view_result = call_user_func_array('views_get_view_result', $params);

        // Do not include paths on which the view returns an empty result.
        if (empty($view_result)) {
          throw new \UnexpectedValueException('The view returned an empty result.');
        }

        // Remove empty arguments from URL.
        $this->cleanRouteParameters($url, $args);
      }

      // Destroy a view instance.
      $view->destroy();
    }
    catch (\Exception $e) {
      // Delete records about arguments that are not added to the sitemap.
      if (!empty($data_set['index_id'])) {
        $condition = Database::getConnection()->condition('AND');
        $condition->condition('id', $data_set['index_id']);
        $this->sitemapViews->removeArgumentsFromIndex($condition);
      }
      throw new SkipElementException($e->getMessage());
    }

    $path_data = $this->constructPathData($url, $settings);

    // Additional info useful in hooks.
    $path_data['meta']['view_info'] = [
      'view_id' => $view_id,
      'display_id' => $display_id,
      'arguments' => $args,
    ];

    return $path_data;
  }

  /**
   * Clears the URL from parameters that are not present in the arguments.
   *
   * @param \Drupal\Core\Url $url
   *   The URL object.
   * @param array $args
   *   Array of arguments.
   *
   * @throws \UnexpectedValueException.
   *   If this is a URI with no corresponding route.
   */
  protected function cleanRouteParameters(Url $url, array $args): void {
    $parameters = $url->getRouteParameters();

    // Check that the number of params does not match the number of arguments.
    if (count($parameters) !== count($args)) {
      $route_name = $url->getRouteName();
      $route = $this->routeProvider->getRouteByName($route_name);
      $variables = $route->compile()->getVariables();

      // Remove params that are not present in the arguments.
      foreach ($variables as $variable_name) {
        if (empty($args)) {
          unset($parameters[$variable_name]);
        }
        else {
          array_shift($args);
        }
      }

      // Set new route params.
      $url->setRouteParameters($parameters);
    }
  }

}
