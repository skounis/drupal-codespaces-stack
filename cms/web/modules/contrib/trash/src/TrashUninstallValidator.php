<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Prevents uninstallation of the Trash module if there is deleted content.
 */
class TrashUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate($module): array {
    $reasons = [];
    if ($module !== 'trash') {
      return $reasons;
    }

    $in_use = [];
    foreach ($this->trashManager->getEnabledEntityTypes() as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $result = $this->entityTypeManager->getStorage($entity_type_id)
        ->getQuery()
        ->accessCheck(FALSE)
        ->exists('deleted')
        ->execute();

      if ($result) {
        $in_use[] = $entity_type->getLabel();
      }
    }

    if ($in_use) {
      $reasons[] = $this->formatPlural(count($in_use),
        'There is deleted content for the %label entity type.',
        'There is deleted content for the following entity types: %label.',
        ['%label' => implode(', ', $in_use)]
      );
    }

    return $reasons;
  }

}
