<?php

namespace Drupal\klaro\Plugin\Filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\klaro\Utility\KlaroHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter for Klaro! Consent Manager.
 *
 * Provides a filter to decorate external sources for the use of Klaro.
 *
 * @Filter(
 *   id = "klaro_filter",
 *   title = @Translation("Klaro! Filter: Decorate external sources"),
 *   description = @Translation("Prevents loading external sources if a matching service is found or the blocking of unknown sources is enabled."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class KlaroFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * Klaro! Helper Service.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $klaroHelper;

  /**
   * Constructs a KlaroFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\klaro\Utility\KlaroHelper $klaro_helper
   *   The Klaro! Helper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, KlaroHelper $klaro_helper) {
    $this->klaroHelper = $klaro_helper;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('klaro.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Klaro! Filter prevents loading external sources
    if a matching service is found or the blocking of unknown sources is
    enabled.');
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    try {
      $text = $this->klaroHelper->processHtml($text);
      $result = new FilterProcessResult($text);
      return $result;
    }
    catch (\Exception $th) {
      throw $th;
    }
  }

}
