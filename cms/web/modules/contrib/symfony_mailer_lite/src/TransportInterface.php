<?php

namespace Drupal\symfony_mailer_lite;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the Mailer Transport interface.
 */
interface TransportInterface extends ConfigEntityInterface {

  /**
   * Returns the transport plugin.
   *
   * @return \Drupal\symfony_mailer_lite\TransportPluginInterface
   *   The transport plugin used by this mailer transport entity.
   */
  public function getPlugin();

  /**
   * Returns the transport plugin ID.
   *
   * @return string
   *   The transport plugin ID.
   */
  public function getPluginId();

  /**
   * Sets the transport plugin.
   *
   * @param string $plugin_id
   *   The transport plugin ID.
   *
   * @return $this
   */
  public function setPluginId($plugin_id);

  /**
   * Gets the DSN.
   *
   * @return string
   *   The DSN.
   */
  public function getDsn();

  /**
   * Sets this as the default transport.
   *
   * @return $this
   */
  public function setAsDefault();

  /**
   * Determines if this is the default transport.
   *
   * @return bool
   *   TRUE if this is the default transport, FALSE otherwise.
   */
  public function isDefault();

}
