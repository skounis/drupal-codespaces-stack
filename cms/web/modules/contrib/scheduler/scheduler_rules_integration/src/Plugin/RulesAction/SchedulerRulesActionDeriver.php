<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\rules\Context\ContextDefinition;
use Drupal\scheduler\SchedulerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives actions for each supported entity type.
 *
 * Based on code from Rules module EntityCreateDeriver.
 */
class SchedulerRulesActionDeriver extends DeriverBase implements ContainerDeriverInterface {

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
      // Initialize to keep PHPStan happy.
      $date_label = $date_description = '';
      // Define the action label, context label and description, depending on
      // which derivative we are building.
      switch ($base_plugin_id) {
        case 'scheduler_set_publishing_date':
          $action_label = $this->t('Set date for publishing a @entity_type_singular', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label for scheduling', $t_args))
            ->setDescription($this->t('The @entity_type_singular which is to have a scheduled publishing date set', $t_args));
          // Define a label and description for the date context definition.
          $date_label = $this->t('Date for publishing');
          $date_description = $this->t('The date when Scheduler will publish the @entity_type_singular', $t_args);
          break;

        case 'scheduler_set_unpublishing_date':
          $action_label = $this->t('Set date for unpublishing a @entity_type_singular', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label for scheduling', $t_args))
            ->setDescription($this->t('The @entity_type_singular which is to have a scheduled unpublishing date set', $t_args));
          $date_label = $this->t('Date for unpublishing');
          $date_description = $this->t('The date when Scheduler will unpublish the @entity_type_singular', $t_args);
          break;

        case 'scheduler_remove_publishing_date':
          $action_label = $this->t('Remove date for publishing a @entity_type_singular', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label', $t_args))
            ->setDescription($this->t('The @entity_type_singular from which to remove the scheduled publishing date', $t_args));
          break;

        case 'scheduler_remove_unpublishing_date':
          $action_label = $this->t('Remove date for unpublishing a @entity_type_singular', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label', $t_args))
            ->setDescription($this->t('The @entity_type_singular from which to remove the scheduled unpublishing date', $t_args));
          break;

        case 'scheduler_publish_now':
          $action_label = $this->t('Publish a @entity_type_singular immediately', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label for publishing', $t_args))
            ->setDescription($this->t('The @entity_type_singular to be published now', $t_args));
          break;

        case 'scheduler_unpublish_now':
          $action_label = $this->t('Unpublish a @entity_type_singular immediately', $t_args);
          $entity_context_definition
            ->setLabel($this->t('@entity_type_label for unpublishing', $t_args))
            ->setDescription($this->t('The @entity_type_singular to be unpublished now', $t_args));
          break;

        default:
          $action_label = 'NOT SET for ' . $base_plugin_id;
          $entity_context_definition->setLabel($action_label);
          break;
      }

      // Build the basic action definition, with the entity context, which is
      // common to all six actions.
      $action_definition = [
        'label' => $action_label,
        'entity_type_id' => $entity_type_id,
        'category' => $entity_type->getLabel() . ' (' . $this->t('Scheduler') . ')',
        // The context parameter names have to be consistent across all entity
        // types (we cannot use $entity_type_id). This avoids PHP8 failing with
        // 'unknown named parameter' in call_user_func_array()
        // @see https://www.drupal.org/project/scheduler/issues/3276637
        'context_definitions' => ['entity' => $entity_context_definition],
      ];

      // For the actions that set a scheduler date add the date as a second
      // context variable.
      if ($base_plugin_id == 'scheduler_set_publishing_date' || $base_plugin_id == 'scheduler_set_unpublishing_date') {
        $date_context_definition = ContextDefinition::create('timestamp')
          ->setLabel($date_label)
          ->setDescription($date_description)
          ->setRequired(TRUE);
        $action_definition['context_definitions']['date'] = $date_context_definition;
      }

      // Finally add the full action definition to the derivatives array.
      $this->derivatives[$entity_type_id] = $action_definition + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
