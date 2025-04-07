<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\trash\Handler\TrashHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Provides the Trash manager.
 */
class TrashManager implements TrashManagerInterface {

  /**
   * One of 'active', 'inactive' or 'ignore'.
   *
   * @var string
   */
  protected $trashContext = 'active';

  public function __construct(
    protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    protected EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository,
    protected ConfigFactoryInterface $configFactory,
    #[AutowireIterator(tag: 'trash_handler', indexAttribute: 'entity_type_id')]
    protected iterable $trashHandlers = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type): bool {
    return is_subclass_of($entity_type->getStorageClass(), SqlEntityStorageInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeEnabled(EntityTypeInterface|string $entity_type, ?string $bundle = NULL): bool {
    $entity_type_id = $entity_type instanceof EntityTypeInterface ? $entity_type->id() : $entity_type;
    $enabled_entity_types = $this->configFactory->get('trash.settings')->get('enabled_entity_types') ?? [];
    if (!isset($enabled_entity_types[$entity_type_id])) {
      return FALSE;
    }
    elseif ($enabled_entity_types[$entity_type_id] === []) {
      return TRUE;
    }
    elseif ($bundle === NULL || in_array($bundle, $enabled_entity_types[$entity_type_id], TRUE)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledEntityTypes(): array {
    return array_keys($this->configFactory->get('trash.settings')->get('enabled_entity_types') ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function enableEntityType(EntityTypeInterface $entity_type): void {
    $field_storage_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type->id());

    if (!$this->isEntityTypeSupported($entity_type)) {
      throw new \InvalidArgumentException("Trash integration can not be enabled for the {$entity_type->id()} entity type.");
    }

    if (isset($field_storage_definitions['deleted'])) {
      if ($field_storage_definitions['deleted']->getProvider() !== 'trash') {
        throw new \InvalidArgumentException("The {$entity_type->id()} entity type already has a 'deleted' field.");
      }
      else {
        throw new \InvalidArgumentException("Trash integration is already enabled for the {$entity_type->id()} entity type.");
      }
    }

    $storage_definition = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Deleted'))
      ->setDescription(t('Time when the item got deleted'))
      ->setInternal(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE);

    $this->entityDefinitionUpdateManager->installFieldStorageDefinition('deleted', $entity_type->id(), 'trash', $storage_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function disableEntityType(EntityTypeInterface $entity_type): void {
    $field_storage_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type->id());
    if (isset($field_storage_definitions['deleted'])) {
      $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($field_storage_definitions['deleted']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function shouldAlterQueries(): bool {
    $trash_context = $this->trashContext ?? 'active';
    return $trash_context !== 'ignore';
  }

  /**
   * {@inheritdoc}
   */
  public function getTrashContext(): string {
    return $this->trashContext ?? 'active';
  }

  /**
   * {@inheritdoc}
   */
  public function setTrashContext(string $context): static {
    assert(in_array($context, ['active', 'inactive', 'ignore'], TRUE));
    $this->trashContext = $context;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function executeInTrashContext($context, callable $function): mixed {
    assert(in_array($context, ['active', 'inactive', 'ignore'], TRUE));

    $this->trashContext = $context;
    $result = $function();
    unset($this->trashContext);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler(string $entity_type_id): ?TrashHandlerInterface {
    $handlers = iterator_to_array($this->trashHandlers);
    if (isset($handlers[$entity_type_id])) {
      return $handlers[$entity_type_id];
    }

    return NULL;
  }

}
