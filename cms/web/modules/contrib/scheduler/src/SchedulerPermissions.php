<?php

namespace Drupal\scheduler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for scheduler plugins.
 */
class SchedulerPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The scheduler manager service.
   *
   * @var SchedulerManager
   */
  private $schedulerManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\scheduler\SchedulerPermissions instance.
   *
   * @param \Drupal\scheduler\SchedulerManager $scheduler_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SchedulerManager $scheduler_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->schedulerManager = $scheduler_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('scheduler.manager'), $container->get('entity_type.manager'));
  }

  /**
   * Build permissions for each entity type.
   *
   * SchedulerManager function permissionName() can be used to return the
   * permission name for a given entity type and permission type.
   *
   * @return array|array[]
   *   The full list of permissions to schedule and to view each entity type.
   */
  public function permissions() {
    $permissions = [];
    $types = $this->schedulerManager->getPluginEntityTypes();
    foreach ($types as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      // For backwards-compatibility with existing permissions, the node
      // permission names have to end with 'nodes' and 'content'. For all other
      // entity types we use $entity_type_id for both permissions.
      if ($entity_type_id == 'node') {
        $edit_key = 'nodes';
        $view_key = 'content';
      }
      else {
        $edit_key = $view_key = $entity_type_id;
      }
      $t_args = [
        '%label' => $entity_type->getLabel(),
        '%singular_label' => $entity_type->getSingularLabel(),
        '%plural_label' => $entity_type->getPluralLabel(),
      ];

      $permissions += [
        "schedule publishing of $edit_key"  => [
          'title' => $this->t('Schedule publishing and unpublishing of %label', $t_args),
          'description' => $this->t('Allows users to set a start and end time for %singular_label publication.', $t_args),
        ],
        "view scheduled $view_key" => [
          'title' => $this->t('View scheduled %label', $t_args),
          'description' => $this->t('Allows users to see a list of all %plural_label that are scheduled.', $t_args),
        ],
      ];
    }
    return $permissions;
  }

}
