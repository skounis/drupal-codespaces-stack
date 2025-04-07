<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for trash-enabled entity types.
 */
class TrashPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('trash.manager')
    );
  }

  /**
   * Returns an array of trash-able entity type permissions.
   *
   * @return array
   *   The entity type permissions.
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function entityTypePermissions(): array {
    $perms = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    // Generate media permissions for all trash-able entity types.
    foreach ($this->trashManager->getEnabledEntityTypes() as $entity_type_id) {
      if (!isset($entity_types[$entity_type_id])) {
        continue;
      }

      $perms += [
        "restore $entity_type_id entities" => [
          'title' => $this->t('Restore %type entities from the trash bin', [
            '%type' => $entity_types[$entity_type_id]->getLabel(),
          ]),
          'description' => $this->t('Users with this permission will be able to restore entities from the trash bin.'),
        ],
        "purge $entity_type_id entities" => [
          'title' => $this->t('Purge %type entities from the trash bin', [
            '%type' => $entity_types[$entity_type_id]->getLabel(),
          ]),
          'description' => $this->t('Users with this permission will be able to permanently delete (purge) trashed entities from the system.'),
        ],
      ];
    }
    return $perms;
  }

}
