<?php

namespace Drupal\project_browser\Plugin\ProjectBrowserSource;

use Composer\InstalledVersions;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A source plugin that only exposes modules installed in the code base.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[ProjectBrowserSource(
  id: 'local_modules',
  label: new TranslatableMarkup('Local modules'),
  description: new TranslatableMarkup('Lists modules already installed via Composer.'),
  local_task: [],
)]
final class LocalModules extends ProjectBrowserSourceBase implements ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ProjectBrowserSourceInterface $decorated,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
    $manager = $container->get(ProjectBrowserSourceManager::class);

    // If we're in a test environment and the mock source is available, query
    // its projects only.
    if (drupal_valid_test_ua() && $manager->hasDefinition('project_browser_test_mock')) {
      $decorated = $manager->createInstance('project_browser_test_mock');
      // Ensure we only query against packages which the mock is aware of.
      $configuration['package_names'] = [];
    }
    else {
      $decorated = $manager->createInstance('drupalorg_jsonapi');
    }
    assert($decorated instanceof ProjectBrowserSourceInterface);
    return new static($decorated, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    return $this->decorated->getFilterDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    // If we're in a test environment, we can use a specific list of package
    // names provided in our configuration.
    if (drupal_valid_test_ua()) {
      $package_names = $this->configuration['package_names'] ?? NULL;
    }
    $package_names ??= InstalledVersions::getInstalledPackagesByType('drupal-module');

    if ($package_names) {
      $module_names = array_map('basename', $package_names);
      $query['machine_name'] = implode(',', array_unique($module_names));
    }
    $results = $this->decorated->getProjects($query);
    return $this->createResultsPage(
      $results->list,
      $results->totalResults,
      $results->error,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    return $this->decorated->getSortOptions();
  }

}
