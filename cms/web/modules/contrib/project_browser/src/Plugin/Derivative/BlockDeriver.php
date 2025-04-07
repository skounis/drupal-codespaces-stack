<?php

declare(strict_types=1);

namespace Drupal\project_browser\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\project_browser\EnabledSourceHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes a block plugin for every enabled source.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class BlockDeriver extends DeriverBase implements ContainerDeriverInterface {

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
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->enabledSources->getCurrentSources() as $id => $source) {
      ['label' => $label] = $source->getPluginDefinition();
      $this->derivatives[$id] = ['admin_label' => $label] + $base_plugin_definition;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
