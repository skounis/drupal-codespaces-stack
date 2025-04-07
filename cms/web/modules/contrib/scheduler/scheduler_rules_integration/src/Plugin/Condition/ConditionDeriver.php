<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\rules\Context\ContextDefinition;
use Drupal\scheduler\SchedulerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives conditions for each supported entity type (except nodes).
 */
class ConditionDeriver extends DeriverBase implements ContainerDeriverInterface {

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
   * Creates a new deriver object.
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
    $base_plugin_id = $base_plugin_definition['id'];
    foreach ($this->schedulerManager->getPluginEntityTypes() as $entity_type_id) {
      // Node actions are the originals, and for backwards-compatibility those
      // action ids must remain the same, which can not be done using this
      // deriver. Hence the node actions are defined in the 'Legacy' classes.
      if ($entity_type_id == 'node') {
        continue;
      }
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Create a context definition object for the 'entity'. This is common
      // to all the derivatives.
      $entity_context_definition = ContextDefinition::create("entity:$entity_type_id")
        ->setAssignmentRestriction(ContextDefinition::ASSIGNMENT_RESTRICTION_SELECTOR)
        ->setRequired(TRUE);

      $t_args = [
        '@entity_type_label' => $entity_type->getLabel(),
        '@entity_type_singular' => $entity_type->getSingularLabel(),
      ];
      // Define the action label, context label and description, depending on
      // which derivative we are building.
      switch ($base_plugin_id) {
        case 'scheduler_publishing_is_enabled':
          $label = $this->t('@entity_type_label type is enabled for scheduled publishing', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label', $t_args))
            ->setDescription($this->t('The @entity_type_singular to check for the type being enabled for scheduled publishing.', $t_args));
          break;

        case 'scheduler_unpublishing_is_enabled':
          $label = $this->t('@entity_type_label type is enabled for scheduled unpublishing', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label', $t_args))
            ->setDescription($this->t('The @entity_type_singular to check for the type being enabled for scheduled unpublishing.', $t_args));
          break;

        case 'scheduler_entity_is_scheduled_for_publishing':
          $label = $this->t('@entity_type_label is scheduled for publishing', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label', $t_args))
            ->setDescription($this->t('The @entity_type_singular to check for having a scheduled publishing date.', $t_args));
          break;

        case 'scheduler_entity_is_scheduled_for_unpublishing':
          $label = $this->t('@entity_type_label is scheduled for unpublishing', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label', $t_args))
            ->setDescription($this->t('The @entity_type_singular to check for having a scheduled unpublishing date.', $t_args));
          break;

        default:
          $label = 'NOT SET for ' . $base_plugin_id;
          $entity_context_definition->setLabel($label);
          break;
      }

      // Build the basic condition definition with the entity context.
      $condition_definition = [
        'label' => $label,
        'entity_type_id' => $entity_type_id,
        'category' => $entity_type->getLabel() . ' (' . $this->t('Scheduler') . ')',
        // The context parameter names have to be consistent across all entity
        // types (we cannot use $entity_type_id). This avoids PHP8 failing with
        // 'unknown named parameter' in call_user_func_array()
        // @see https://www.drupal.org/project/scheduler/issues/3276637
        'context_definitions' => ['entity' => $entity_context_definition],
      ];

      // Add the full definition to the derivatives array.
      $this->derivatives[$entity_type_id] = $condition_definition + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
