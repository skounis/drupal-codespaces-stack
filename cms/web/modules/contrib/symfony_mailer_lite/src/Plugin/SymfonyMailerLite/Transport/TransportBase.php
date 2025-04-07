<?php

namespace Drupal\symfony_mailer_lite\Plugin\SymfonyMailerLite\Transport;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\symfony_mailer_lite\TransportPluginInterface;

/**
 * Base class for Mailer Transport plug-ins.
 */
abstract class TransportBase extends PluginBase implements TransportPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getDsn() {
    $cfg = $this->configuration;
    $default_cfg = $this->defaultConfiguration();

    // Remove default values from query string.
    $query = !empty($cfg['query']) ? array_diff_assoc($cfg['query'], $default_cfg['query']) : [];

    $dsn = $this->getPluginId() . '://' .
      (!empty($cfg['user']) ? urlencode($cfg['user']) : '') .
      (!empty($cfg['pass']) ? ':' . urlencode($cfg['pass']) : '') .
      (!empty($cfg['user']) ? '@' : '') .
      (urlencode($cfg['host'] ?? 'default')) .
      (isset($cfg['port']) ? ':' . $cfg['port'] : '') .
      ($query ? '?' . http_build_query($query) : '');

    return $dsn;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
