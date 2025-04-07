<?php

namespace Drupal\dashboard\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutBuilderSampleEntityGenerator;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Drupal\layout_builder\Routing\LayoutBuilderRoutesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines the 'dashboard' section storage type.
 *
 * @SectionStorage(
 *   id = "dashboard",
 *   context_definitions = {
 *     "dashboard" = @ContextDefinition("entity:dashboard")
 *   }
 * )
 *
 * @internal
 *   Plugin classes are internal.
 */
class DashboardSectionStorage extends SectionStorageBase implements ContainerFactoryPluginInterface {

  use ContextAwarePluginTrait;
  use LayoutBuilderRoutesTrait;

  /**
   * An array of sections.
   *
   * @var \Drupal\layout_builder\Section[]|null
   */
  protected $sections;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LayoutBuilderSampleEntityGenerator $sampleEntityGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('layout_builder.sample_entity_generator')
    );
  }

  /**
   * Gets the dashboard.
   *
   * @return \Drupal\layout_builder\SectionListInterface|\Drupal\dashboard\Entity\Dashboard
   *   Layout.
   */
  protected function getDashboard() {
    return $this->getSectionList();
  }

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {
    return $this->getContextValue('dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageType() {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    return $this->getDashboard()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function isLayoutBuilderEnabled() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {
    $options = [];
    $options['_admin_route'] = TRUE;
    $options['parameters']['dashboard']['type'] = 'entity:dashboard';

    $this->buildLayoutRoutes($collection, $this->getPluginDefinition(), 'admin/structure/dashboard/{dashboard}/layout', [], [], $options, '', 'dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    return Url::fromRoute('entity.dashboard.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {
    return Url::fromRoute("layout_builder.{$this->getStorageType()}.$rel", ['dashboard' => $this->getStorageId()]);
  }

  /**
   * Extracts an entity from the route values.
   *
   * @param mixed $value
   *   The raw value from the route.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity for the route, or NULL if none exist.
   */
  protected function extractEntityFromRoute($value, array $defaults) {
    return $this->entityTypeManager->getStorage('dashboard')->load($value ?: $defaults['dashboard']);
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    $contexts = [];

    if ($entity = $this->extractEntityFromRoute($value, $defaults)) {
      $contexts['dashboard'] = EntityContext::fromEntity($entity);
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getStorageId();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    return $this->getDashboard()->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(RefinableCacheableDependencyInterface $cacheability) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getContextsDuringPreview() {
    return $this->getContexts();
  }

}
