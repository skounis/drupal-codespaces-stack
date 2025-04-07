<?php

namespace Drupal\date_augmenter\DateAugmenter;

use Drupal\date_augmenter\Plugin\ConfigurablePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other augmenters may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_date_augmenter_augmenter_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the augmenter.
 * - label: The human-readable name of the augmenter, translated.
 * - description: A human-readable description for the augmenter, translated.
 * - weight: The default weight at which the augmenter should run.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @DateAugmenter(
 *   id = "my_augmenter",
 *   label = @Translation("My DateAugmenter"),
 *   description = @Translation("Does … something."),
 *   weight = 0,
 * )
 * @endcode
 *
 * @see \Drupal\date_augmenter\Annotation\DateAugmenter
 * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginManager
 * @see \Drupal\date_augmenter\DateAugmenter\DateAugmenterInterface
 * @see plugin_api
 */
abstract class DateAugmenterPluginBase extends ConfigurablePluginBase implements DateAugmenterInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $augmenter */
    $augmenter = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    return $augmenter;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    if (isset($this->configuration['weight'])) {
      return $this->configuration['weight'];
    }
    $plugin_definition = $this->getPluginDefinition();
    return (int) ($plugin_definition['weight'] ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->configuration['weight'] = $weight;
    return $this;
  }

}
