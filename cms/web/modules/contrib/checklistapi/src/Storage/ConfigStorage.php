<?php

namespace Drupal\checklistapi\Storage;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides config-based checklist progress storage.
 */
class ConfigStorage extends StorageBase {

  /**
   * The configuration key for saved progress.
   */
  const CONFIG_KEY = 'progress';

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config|null
   */
  private $config;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs a class instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getSavedProgress() {
    return $this->getConfig()->get(self::CONFIG_KEY);
  }

  /**
   * {@inheritdoc}
   */
  public function setSavedProgress(array $progress) {
    $this->getConfig()->set(self::CONFIG_KEY, $progress)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSavedProgress() {
    $this->getConfig()->delete();
  }

  /**
   * Gets the config object.
   *
   * @return \Drupal\Core\Config\Config
   *   Returns the config object.
   */
  private function getConfig() {
    if (empty($this->config)) {
      $this->config = $this->configFactory
        ->getEditable("checklistapi.progress.{$this->getChecklistId()}");
    }
    return $this->config;
  }

}
