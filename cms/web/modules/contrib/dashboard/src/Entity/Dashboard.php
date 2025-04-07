<?php

namespace Drupal\dashboard\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Url;
use Drupal\dashboard\DashboardInterface;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionListTrait;

/**
 * Defines the dashboard entity type.
 *
 * @ConfigEntityType(
 *   id = "dashboard",
 *   label = @Translation("Dashboard"),
 *   label_collection = @Translation("Dashboards"),
 *   label_singular = @Translation("dashboard"),
 *   label_plural = @Translation("dashboards"),
 *   label_count = @PluralTranslation(
 *     singular = "@count dashboard",
 *     plural = "@count dashboards",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\dashboard\DashboardAccessControlHandler",
 *     "storage" = "Drupal\dashboard\DashboardStorageHandler",
 *     "list_builder" = "Drupal\dashboard\DashboardListBuilder",
 *     "form" = {
 *       "add" = "Drupal\dashboard\Form\DashboardForm",
 *       "edit" = "Drupal\dashboard\Form\DashboardForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *       "layout_builder" = "Drupal\dashboard\Form\DashboardLayoutBuilderForm"
 *     }
 *   },
 *   config_prefix = "dashboard",
 *   admin_permission = "administer dashboard",
 *   links = {
 *     "collection" = "/admin/structure/dashboard",
 *     "add-form" = "/admin/structure/dashboard/add",
 *     "edit-form" = "/admin/structure/dashboard/{dashboard}",
 *     "delete-form" = "/admin/structure/dashboard/{dashboard}/delete",
 *     "canonical" = "/admin/dashboard/{dashboard}",
 *     "preview" = "/admin/structure/dashboard/{dashboard}/preview"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "layout",
 *     "weight"
 *   }
 * )
 */
class Dashboard extends ConfigEntityBase implements DashboardInterface, SectionListInterface {

  use SectionListTrait;

  /**
   * The dashboard ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The dashboard label.
   *
   * @var string
   */
  protected $label;

  /**
   * The dashboard status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The dashboard description.
   *
   * @var string
   */
  protected $description;

  /**
   * The dashboard weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * Layout.
   *
   * @var array
   */
  protected $layout = [];

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->get('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    return $this->layout;
  }

  /**
   * Stores the information for all sections.
   *
   * Implementations of this method are expected to call array_values() to rekey
   * the list of sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of section objects.
   *
   * @return $this
   */
  protected function setSections(array $sections) {
    $this->layout = array_values($sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'layout-builder', array $options = []) {
    if ($rel === 'layout-builder') {
      $options += [
        'language' => NULL,
        'entity_type' => 'dashboard',
        'entity' => $this,
      ];
      $parameters['dashboard'] = $this->id();
      $uri = new Url("layout_builder.{$this->getEntityTypeId()}.view", $parameters);
      $uri->setOptions($options);
      return $uri;
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    Cache::invalidateTags(['local_task']);
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add dependency on Layout Builder module if layout is not empty.
    if (!empty($this->get('layout'))) {
      $this->addDependency('module', 'layout_builder');
    }

    // Calculate nested blocks dependencies and include them.
    foreach ($this->getSections() as $section) {
      $this->calculatePluginDependencies($section->getLayout());
      foreach ($section->getComponents() as $component) {
        $this->calculatePluginDependencies($component->getPlugin());
      }
    }

    return $this;
  }

}
