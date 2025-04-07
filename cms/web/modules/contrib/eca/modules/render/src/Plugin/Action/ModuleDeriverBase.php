<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for derivers that depend on installed modules.
 */
abstract class ModuleDeriverBase extends DeriverBase implements ContainerDeriverInterface {

  /**
   * A list of modules, that are required.
   *
   * @var array
   */
  protected static array $requiredModules = [];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs the ModuleDeriver base class.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  final public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): ModuleDeriverBase {
    return new static($container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    if (empty($this->derivatives)) {
      $required_modules_installed = TRUE;
      foreach (static::$requiredModules as $name) {
        if (!$this->moduleHandler->moduleExists($name)) {
          $required_modules_installed = FALSE;
          break;
        }
      }
      if ($required_modules_installed) {
        $this->buildDerivativeDefinitions($base_plugin_definition);
      }
    }
    return $this->derivatives;
  }

  /**
   * Builds up the derivative definitions.
   *
   * The definitions are to be added to the "derivatives" property.
   *
   * @param array $base_plugin_definition
   *   The definition array of the base plugin.
   */
  abstract protected function buildDerivativeDefinitions(array $base_plugin_definition): void;

}
