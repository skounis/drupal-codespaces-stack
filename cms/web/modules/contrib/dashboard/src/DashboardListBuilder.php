<?php

namespace Drupal\dashboard;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of dashboards.
 */
class DashboardListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_dashboard_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\dashboard\DashboardInterface $entity */
    $row['label'] = $entity->label();
    $row['machine_name']['#markup'] = $entity->id();
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\dashboard\Entity\Dashboard $entity */
    $operations = [];
    $operations['edit_layout'] = [
      'title' => $this->t('Edit layout'),
      'weight' => 0,
      'url' => $this->getLayoutBuilderUrl($entity),
    ];
    $operations['preview'] = [
      'title' => $this->t('Preview'),
      'weight' => 0,
      'url' => $this->getPreviewUrl($entity),
    ];
    $operations['manage_permission'] = [
      'title' => $this->t('Manage permissions'),
      'weight' => 25,
      'url' => Url::fromRoute('entity.dashboard.permissions_form', ['dashboard' => $entity->id()]),
    ];

    return $operations + parent::getDefaultOperations($entity);
  }

  /**
   * Retrieve the layout builder URL for the given dashboard entity.
   *
   * @param \Drupal\dashboard\DashboardInterface $dashboard
   *   The dashboard entity.
   */
  protected function getLayoutBuilderUrl(DashboardInterface $dashboard) {
    return Url::fromRoute("layout_builder.dashboard.view", $this->getRouteParameters($dashboard));
  }

  /**
   * Retrieve the preview URL for the given dashboard entity.
   *
   * @param \Drupal\dashboard\DashboardInterface $dashboard
   *   The dashboard entity.
   */
  protected function getPreviewUrl(DashboardInterface $dashboard) {
    return Url::fromRoute("entity.dashboard.preview", $this->getRouteParameters($dashboard));
  }

  /**
   * Retrieve the route parameters from the given dashboard entity.
   *
   * @param \Drupal\dashboard\DashboardInterface $dashboard
   *   The dashboard entity.
   */
  protected function getRouteParameters(DashboardInterface $dashboard) {
    $route_parameters = [];
    $route_parameters['dashboard'] = $dashboard->id();
    return $route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('The dashboard settings have been updated.'));
  }

}
