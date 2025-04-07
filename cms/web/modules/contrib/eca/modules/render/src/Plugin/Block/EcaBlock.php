<?php

namespace Drupal\eca_render\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Event\TriggerEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ECA Block plugin.
 *
 * @Block(
 *   id = "eca",
 *   admin_label = @Translation("ECA Block"),
 *   category = @Translation("ECA"),
 *   deriver = "Drupal\eca_render\Plugin\Block\EcaBlockDeriver"
 * )
 */
final class EcaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The build of the render array.
   *
   * @var array
   */
  public array $build = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The service for triggering ECA-related events.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): EcaBlock {
    return new EcaBlock(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('eca.trigger_event')
    );
  }

  /**
   * The EcaBlock constructor.
   *
   * @param array $configuration
   *   The settings configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\eca\Event\TriggerEvent $trigger_event
   *   The service for triggering ECA-related events.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TriggerEvent $trigger_event) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->triggerEvent = $trigger_event;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $event = $this->triggerEvent->dispatchFromPlugin('eca_render:block', $this);
    if ($event instanceof RenderEventInterface) {
      $this->build = &$event->getRenderArray();
    }
    return ['content' => $this->build];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    foreach ($this->getEcaConfigurations() as $eca) {
      $dependencies[$eca->getConfigDependencyKey()][] = $eca->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * Get the ECA configurations, that define this block.
   *
   * @return \Drupal\eca\Entity\Eca[]
   *   The ECA configurations, keyed by config entity ID.
   */
  public function getEcaConfigurations(): array {
    $block_event_name = $this->getDerivativeId();
    $configs = [];
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->entityTypeManager->getStorage('eca')->loadMultiple() as $eca) {
      if (!$eca->status()) {
        continue;
      }
      foreach (($eca->get('events') ?? []) as $event) {
        if ($event['plugin'] !== 'eca_render:block') {
          continue;
        }
        $configured_event_machine_name = $event['configuration']['block_machine_name'] ?? '';
        if ($configured_event_machine_name === $block_event_name) {
          $configs[$eca->id()] = $eca;
        }
      }
    }
    return $configs;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Add some sensible defaults for cache contexts.
    return array_unique(array_merge([
      'url.path',
      'url.query_args',
      'user',
      'user.permissions',
    ], parent::getCacheContexts()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Add ECA config as cache tag for automatic invalidation.
    return array_unique(array_merge(['config:eca_list'], parent::getCacheTags()));
  }

}
