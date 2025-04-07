<?php

declare(strict_types=1);

namespace Drupal\trash\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\trash\TrashManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\Input;

/**
 * Drush commands for the Trash module.
 */
final class TrashCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
  ) {
    parent::__construct();
  }

  /**
   * Restores trashed entities.
   */
  #[CLI\Command(name: 'trash:restore', aliases: ['tr'])]
  #[CLI\Argument(name: 'entity_type_id', description: 'The entity type to restore.')]
  #[CLI\Argument(name: 'entity_ids', description: 'A comma-separated list of entity IDs to restore.')]
  #[CLI\Option(name: 'all', description: 'Restore data for all entity types.')]
  public function restore(?string $entity_type_id = NULL, $entity_ids = NULL, array $options = ['all' => FALSE]): void {
    $entity_ids = StringUtils::csvToArray($entity_ids);
    $this->getConfirmation('restore', $entity_type_id, $entity_ids, $options);

    if ($options['all']) {
      $entity_type_ids = $this->trashManager->getEnabledEntityTypes();
      $entity_ids = NULL;
    }
    else {
      $entity_type_ids = [$entity_type_id];
    }

    $count = $this->performOperation('restore', $entity_type_ids, $entity_ids);

    if ($count > 0) {
      $this->io()->success(dt('Restored @count trashed entities.', ['@count' => $count]));
    }
    else {
      $this->io()->success(dt('No trashed entities to restore.'));
    }
  }

  /**
   * Purges trashed entities.
   */
  #[CLI\Command(name: 'trash:purge', aliases: ['tp'])]
  #[CLI\Argument(name: 'entity_type_id', description: 'The entity type to purge.')]
  #[CLI\Argument(name: 'entity_ids', description: 'A comma-separated list of entity IDs to purge.')]
  #[CLI\Option(name: 'all', description: 'Purge data for all entity types.')]
  public function purge(?string $entity_type_id = NULL, $entity_ids = NULL, array $options = ['all' => FALSE]): void {
    $entity_ids = StringUtils::csvToArray($entity_ids);
    $this->getConfirmation('purge', $entity_type_id, $entity_ids, $options);

    if ($options['all']) {
      $entity_type_ids = $this->trashManager->getEnabledEntityTypes();
      $entity_ids = NULL;
    }
    else {
      $entity_type_ids = [$entity_type_id];
    }

    $count = $this->performOperation('purge', $entity_type_ids, $entity_ids);

    if ($count > 0) {
      $this->io()->success(dt('Purged @count trashed entities.', ['@count' => $count]));
    }
    else {
      $this->io()->success(dt('No trashed entities to purge.'));
    }
  }

  /**
   * Asks the user to select an entity type.
   */
  #[CLI\Hook(type: HookManager::INTERACT, target: 'trash:restore')]
  public function hookInteractRestore(Input $input): void {
    if (!$input->getArgument('entity_type_id') && !$input->getOption('all')) {
      $entity_type_ids = $this->trashManager->getEnabledEntityTypes();

      if ($entity_type_ids !== []) {
        if (!$choice = $this->io()->select('Select the entity type you want to restore.', array_combine($entity_type_ids, $entity_type_ids))) {
          throw new UserAbortException();
        }

        $input->setArgument('entity_type_id', $choice);
      }
      else {
        throw new CommandFailedException(dt('No entity types enabled.'));
      }
    }
  }

  /**
   * Asks the user to select an entity type.
   */
  #[CLI\Hook(type: HookManager::INTERACT, target: 'trash:purge')]
  public function hookInteractPurge(Input $input): void {
    if (!$input->getArgument('entity_type_id') && !$input->getOption('all')) {
      $entity_type_ids = $this->trashManager->getEnabledEntityTypes();

      if ($entity_type_ids !== []) {
        if (!$choice = $this->io()->select('Select the entity type you want to purge.', array_combine($entity_type_ids, $entity_type_ids))) {
          throw new UserAbortException();
        }

        $input->setArgument('entity_type_id', $choice);
      }
      else {
        throw new CommandFailedException(dt('No entity types enabled.'));
      }
    }
  }

  /**
   * Prompts the user to confirm the command arguments.
   */
  protected function getConfirmation($operation, ?string $entity_type_id = NULL, ?array $entity_ids = NULL, array $options = ['all' => FALSE]): void {
    if ($options['all']) {
      if (!$this->io()->confirm(dt('Are you sure you want to @operation all data for all entity types?', [
        '@operation' => $operation,
      ]))) {
        throw new UserAbortException();
      }
    }
    else {
      if (!$entity_ids) {
        if (!$this->io()->confirm(dt('Are you sure you want to @operation all data for the @entity_type_id entity type?', [
          '@operation' => $operation,
          '@entity_type_id' => $entity_type_id,
        ]))) {
          throw new UserAbortException();
        }
      }
      else {
        if (!$this->io()->confirm(dt('Are you sure you want to @operation @entity_type_id @entity_ids?', [
          '@operation' => $operation,
          '@entity_type_id' => $entity_type_id,
          '@entity_ids' => implode(', ', $entity_ids),
        ]))) {
          throw new UserAbortException();
        }
      }
    }
  }

  /**
   * Performs a restore or purge operation on the given arguments.
   *
   * @param string $operation
   *   The operation to perform, either 'restore' or 'purge'.
   * @param array $entity_type_ids
   *   An array of entity type IDs.
   * @param array|null $entity_ids
   *   An array of entity IDs.
   *
   * @return int
   *   Returns the number of entities on which the operation was performed.
   */
  protected function performOperation(string $operation, array $entity_type_ids, ?array $entity_ids = NULL): int {
    assert(in_array($operation, ['restore', 'purge'], TRUE));

    $count = 0;
    foreach ($entity_type_ids as $entity_type_id) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->addMetaData('trash', 'inactive')
        ->exists('deleted');

      if (count($entity_type_ids) === 1 && $entity_ids) {
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        $query->condition($entity_type->getKey('id'), $entity_ids, 'IN');
      }

      $ids = $query->execute();
      if ($ids === []) {
        continue;
      }

      $this->io()->progressStart(count($ids));
      $chunkSize = Settings::get('entity_update_batch_size', 50);

      foreach (array_chunk($ids, $chunkSize) as $chunk) {
        $this->trashManager->executeInTrashContext('inactive', function () use (&$count, $storage, $chunk, $operation) {
          $entities = $storage->loadMultiple($chunk);
          if ($operation === 'restore') {
            // @phpstan-ignore-next-line
            $storage->restoreFromTrash($entities);
          }
          elseif ($operation === 'purge') {
            $storage->delete($entities);
          }
          $count += count($entities);
          $this->io()->progressAdvance(count($chunk));
        });
      }

      $this->io()->progressFinish();
    }

    return $count;
  }

}
