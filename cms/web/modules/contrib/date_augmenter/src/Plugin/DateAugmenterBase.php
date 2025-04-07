<?php

namespace Drupal\date_augmenter\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Date augmenter plugins.
 */
abstract class DateAugmenterBase extends PluginBase implements DateAugmenterInterface {
  use StringTranslationTrait;

  /**
   * The date augmenter process Service.
   *
   * @var array
   */
  protected $processService;

  /**
   * The date augmenter configuration.
   *
   * @var array
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected $output;

  /**
   * {@inheritdoc}
   */
  public function __construct(
                                array $configuration,
                                $plugin_id,
                                $plugin_definition
                              ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
                                  ContainerInterface $container,
                                  array $configuration,
                                  $plugin_id,
                                  $plugin_definition
                                ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settings($setting_key = NULL) {
    if (!is_null($setting_key)) {
      return $this->pluginDefinition['settings'][$setting_key] ?? $this->pluginDefinition['settings'];
    }
    return $this->pluginDefinition['settings'];
  }

  /**
   * Builds and returns a render array for the task.
   *
   * @param array $output
   *   The existing render array, to be augmented.
   * @param Drupal\Core\Datetime\DrupalDateTime $start
   *   The object which contains the start time.
   * @param Drupal\Core\Datetime\DrupalDateTime $end
   *   The optionalobject which contains the end time.
   * @param array $options
   *   An array of options to further guide output.
   */
  abstract public function augmentOutput(array &$output, DrupalDateTime $start, DrupalDateTime $end = NULL, array $options = []);

}
