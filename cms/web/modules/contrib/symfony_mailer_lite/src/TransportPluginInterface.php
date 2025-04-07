<?php

namespace Drupal\symfony_mailer_lite;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for MailerTransport plugins.
 */
interface TransportPluginInterface extends ConfigurableInterface, PluginInspectionInterface, PluginFormInterface {

  /**
   * Gets the DSN.
   *
   * @return string
   *   The DSN.
   */
  public function getDsn();

}
