<?php

namespace Drupal\eca_render\Plugin\Block;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for ECA Block plugins.
 */
final class EcaBlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): EcaBlockDeriver {
    return new EcaBlockDeriver($container->get('entity_type.manager'));
  }

  /**
   * The EcaBlockDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    if (empty($this->derivatives)) {
      /**
       * @var \Drupal\eca\Entity\Eca $eca
       */
      foreach ($this->entityTypeManager->getStorage('eca')->loadMultiple() as $eca) {
        if (!$eca->status()) {
          continue;
        }
        foreach (($eca->get('events') ?? []) as $event) {
          if ($event['plugin'] !== 'eca_render:block') {
            continue;
          }
          $configured_event_name = $event['configuration']['block_name'] ?? '';
          $configured_event_machine_name = $event['configuration']['block_machine_name'] ?? '';
          if ($configured_event_machine_name !== '') {
            $this->derivatives[$configured_event_machine_name] = [
              'label' => $configured_event_name,
              'admin_label' => $configured_event_name,
            ] + $base_plugin_definition;
          }
        }
      }
      if (!empty($this->derivatives)) {
        $context_definitions = [];
        foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
          if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
            $context_definitions[$entity_type->id()] = EntityContextDefinition::fromEntityType($entity_type)
              ->setRequired(FALSE);
          }
        }
        foreach ($this->derivatives as &$derivative) {
          $derivative['context_definitions'] = $context_definitions;
        }
        unset($derivative);
      }
    }
    return $this->derivatives;
  }

}
