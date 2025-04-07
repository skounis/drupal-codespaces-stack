<?php

declare(strict_types=1);

namespace Drupal\project_browser\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\project_browser\EnabledSourceHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes local tasks for all enabled source plugins.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class LocalTaskDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EnabledSourceHandler $enabledSources,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get(EnabledSourceHandler::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $i = 5;
    foreach ($this->enabledSources->getCurrentSources() as $source) {
      $source_definition = $source->getPluginDefinition();

      if (isset($source_definition['local_task'])) {
        $local_task = $base_plugin_definition + $source_definition['local_task'];
        // If no title was provided for the local task, fall back to the
        // source's administrative label.
        $local_task += [
          'title' => $source_definition['label'],
          'weight' => $i++,
        ];
        $source_id = $source->getPluginId();
        $local_task['route_parameters'] = [
          'source' => $source_id,
        ];
        $derivative_id = str_replace(PluginBase::DERIVATIVE_SEPARATOR, '__', $source_id);
        $this->derivatives[$derivative_id] = $local_task;
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
