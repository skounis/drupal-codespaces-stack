<?php

namespace Drupal\scheduler_rules_integration\Event;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\scheduler\SchedulerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives Rules events for all non-node entities supported by Scheduler.
 *
 * This creates events with names starting with a prefix of "scheduler:" as
 * defined by the property name in scheduler_rules_integration.rules.events.yml,
 * followed by the text in keys of the array $this->derivatives.
 *
 * The processing below is based on code in the Rules module. For an example see
 * src/Plugin/RulesEvent/EntityUpdateDeriver.php. For backwards compatibility
 * the node event names must remain unchanged, and this is not possible when
 * using this deriver. Hence the node event names stay written out long-hand in
 * scheduler_rules_integration.rules.events.yml.
 */
class EventDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The scheduler manager.
   *
   * @var \Drupal\scheduler\SchedulerManager
   */
  protected $schedulerManager;

  /**
   * Creates a new EventDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\scheduler\SchedulerManager $scheduler_manager
   *   The scheduler manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, SchedulerManager $scheduler_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->schedulerManager = $scheduler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('scheduler.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Get all entity types supported by Scheduler plugins.
    foreach ($this->schedulerManager->getPluginEntityTypes() as $entity_type_id) {
      // Node events are the originals, and for backwards-compatibility those
      // event ids must remain unchanged, which cannot be done with the deriver.
      // So they remain defined in scheduler_rules_integration.rules.events.yml.
      if ($entity_type_id == 'node') {
        continue;
      }
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Define the values that are the same for all events of this entity type.
      $defaults = [
        'entity_type_id' => $entity_type_id,
        'category' => $entity_type->getLabel() . ' (' . $this->t('Scheduler') . ')',
        'context_definitions' => [
          $entity_type_id => [
            'type' => "entity:$entity_type_id",
            'label' => $this->t('The object representing the scheduled @entity_type', ['@entity_type' => $entity_type->getLabel()]),
          ],
        ],
      ];

      // Create six events for this entity type.
      $this->derivatives["new_{$entity_type_id}_is_scheduled_for_publishing"] = [
        'label' => $this->t('After saving a new @entity_type that is scheduled for publishing', ['@entity_type' => $entity_type->getSingularLabel()]),
      ] + $defaults + $base_plugin_definition;

      $this->derivatives["new_{$entity_type_id}_is_scheduled_for_unpublishing"] = [
        'label' => $this->t('After saving a new @entity_type that is scheduled for unpublishing', ['@entity_type' => $entity_type->getSingularLabel()]),
      ] + $defaults + $base_plugin_definition;

      $this->derivatives["existing_{$entity_type_id}_is_scheduled_for_publishing"] = [
        'label' => $this->t('After updating a @entity_type that is scheduled for publishing', ['@entity_type' => $entity_type->getSingularLabel()]),
      ] + $defaults + $base_plugin_definition;

      $this->derivatives["existing_{$entity_type_id}_is_scheduled_for_unpublishing"] = [
        'label' => $this->t('After updating a @entity_type that is scheduled for unpublishing', ['@entity_type' => $entity_type->getSingularLabel()]),
      ] + $defaults + $base_plugin_definition;

      $this->derivatives["{$entity_type_id}_has_been_published_via_cron"] = [
        'label' => $this->t('After Scheduler has published a @entity_type', ['@entity_type' => $entity_type->getSingularLabel()]),
      ] + $defaults + $base_plugin_definition;

      $this->derivatives["{$entity_type_id}_has_been_unpublished_via_cron"] = [
        'label' => $this->t('After Scheduler has unpublished a @entity_type', ['@entity_type' => $entity_type->getSingularLabel()]),
      ] + $defaults + $base_plugin_definition;

    }
    return $this->derivatives;
  }

}
