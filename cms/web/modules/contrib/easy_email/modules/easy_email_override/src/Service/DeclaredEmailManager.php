<?php

namespace Drupal\easy_email_override\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\easy_email_override\Plugin\Email\Email;

/**
 * Manages discovery and instantiation of email plugins.
 *
 * @see \Drupal\easy_email_override\Plugin\Email\EmailInterface
 * @see plugin_api
 */
class DeclaredEmailManager extends DefaultPluginManager implements DeclaredEmailManagerInterface {


  /**
   * Default values for each workflow plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'module' => '',
    'key' => '',
    'weight' => 0,
    'params' => [],
  ];

  /**
   * Constructs a new EmailOverrideManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'easy_email_override', ['easy_email_override']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('emails', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions =  parent::getDefinitions();
    $definitions = array_filter($definitions, function ($definition) {
      return !empty($definition['module']) && (($definition['module'] === '*') || $this->moduleHandler->moduleExists($definition['module']));
    });
    foreach ($definitions as $i => $definition) {
      if (!empty($definition['params'])) {
        foreach ($definition['params'] as $param_id => $param) {
          if (!empty($param['label'])) {
            $definitions[$i]['params'][$param_id]['label'] = new TranslatableMarkup($definitions[$i]['params'][$param_id]['label']);
          }
        }
      }
      if (!isset($definitions[$i]['weight'])) {
        $definitions[$i]['weight'] = 0;
      }
    }
    uasort($definitions, function ($a, $b) {
      return $a['weight'] <=> $b['weight'] ?: strnatcasecmp($a['label'], $b['label']);
    });
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    $plugin_definition = $this->getDefinition($plugin_id);
    return new Email($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    $definition['id'] = $plugin_id;
    foreach (['label', 'module', 'key'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The email %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }


}
