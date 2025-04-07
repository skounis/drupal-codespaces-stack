<?php

namespace Drupal\symfony_mailer_lite\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\symfony_mailer_lite\TransportInterface;

/**
 * Defines a Mailer Transport configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "symfony_mailer_lite_transport",
 *   label = @Translation("Drupal Symfony Mailer Lite Transport"),
 *   handlers = {
 *     "list_builder" = "Drupal\symfony_mailer_lite\TransportListBuilder",
 *     "form" = {
 *       "edit" = "Drupal\symfony_mailer_lite\Form\TransportForm",
 *       "add" = "Drupal\symfony_mailer_lite\Form\TransportAddForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "access" = "Drupal\symfony_mailer_lite\TransportAccessControlHandler",
 *   },
 *   admin_permission = "administer symfony_mailer_lite configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/symfony-mailer-lite/transport/{symfony_mailer_lite_transport}",
 *     "delete-form" = "/admin/config/system/symfony-mailer-lite/transport/{symfony_mailer_lite_transport}/delete",
 *     "set-default" = "/admin/config/system/symfony-mailer-lite/transport/{symfony_mailer_lite_transport}/set-default",
 *     "collection" = "/admin/config/system/symfony-mailer-lite/transport",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "configuration",
 *   }
 * )
 */
class Transport extends ConfigEntityBase implements TransportInterface, EntityWithPluginCollectionInterface {

  /**
   * The unique ID of the transport.
   *
   * @var string
   */
  protected $id = NULL;

  /**
   * The label of the transport.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin instance ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin instance configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The plugin collection that holds the plugin for this entity.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * Encapsulates the creation of the block's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The block's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new DefaultSingleLazyPluginCollection(\Drupal::service('plugin.manager.symfony_mailer_lite_transport'), $this->plugin, $this->configuration);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'configuration' => $this->getPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin) {
    $this->plugin = $plugin;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDsn() {
    return $this->getPlugin()->getDsn();
  }

  /**
   * {@inheritdoc}
   */
  public function setAsDefault() {
    \Drupal::configFactory()->getEditable('symfony_mailer_lite.settings')->set('default_transport', $this->id())->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return \Drupal::config('symfony_mailer_lite.settings')->get('default_transport') == $this->id();
  }

  /**
   * Gets the default transport.
   *
   * @return \Drupal\symfony_mailer_lite\TransportInterface
   *   The default transport.
   */
  public static function loadDefault() {
    $id = \Drupal::config('symfony_mailer_lite.settings')->get('default_transport');
    return $id ? static::load($id) : NULL;
  }

}
