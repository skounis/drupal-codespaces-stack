<?php

namespace Drupal\dashboard\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\dashboard\DashboardManager;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Dashboard routes.
 */
class DashboardController extends ControllerBase {

  use LayoutBuilderContextTrait;

  /**
   * Constructs a new DashboardController instance.
   *
   * @param \Drupal\dashboard\DashboardManager $dashboardManager
   *   The dashboard manager.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $sectionStorageManager
   *   The section storage manager.
   */
  public function __construct(
    protected DashboardManager $dashboardManager,
    protected SectionStorageManagerInterface $sectionStorageManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dashboard.manager'),
      $container->get('plugin.manager.layout_builder.section_storage')
    );
  }

  /**
   * Access callback for the Dashboard page.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the user is allowed to access or not.
   */
  public function access() {
    $dashboard_exists = $this->dashboardManager->getDefaultDashboard() !== NULL;
    return AccessResult::allowedIf($dashboard_exists)
      ->cachePerUser();
  }

  /**
   * Builds the response.
   */
  public function build(?Dashboard $dashboard) {
    $build = [];
    /** @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $sectionStorageManager */
    if ($dashboard === NULL) {
      $dashboard = $this->dashboardManager->getDefaultDashboard();
    }

    if ($dashboard !== NULL) {
      $contexts = [];
      $contexts['dashboard'] = EntityContext::fromEntity($dashboard);

      $section_storage = $this->sectionStorageManager->load('dashboard', $contexts);

      $build['dashboard'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'dashboard',
            Html::getClass('dashboard--' . $dashboard->id()),
          ],
        ],
        '#attached' => [
          'library' => ['dashboard/dashboard'],
        ],
      ];

      foreach ($section_storage->getSections() as $delta => $section) {
        $contexts = $this->getPopulatedContexts($section_storage);
        $build['dashboard'][$delta] = $section->toRenderArray($contexts);
      }
    }
    else {
      $build['dashboard'] = [
        '#type' => 'item',
        '#markup' => $this->t('There is no dashboard to show.'),
      ];
    }
    return $build;
  }

}
