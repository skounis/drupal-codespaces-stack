<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for purging the trash bin.
 */
#[QueueWorker(
  id: 'trash_entity_purge',
  title: new TranslatableMarkup('Trash Entity Purge Worker'),
  cron: ['time' => 60]
)]
class TrashEntityPurgeWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('trash.manager'),
      $container->get('logger.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
      $entity_definition = $this->entityTypeManager->getDefinition($data['entity_type_id']);
      $id_key = $entity_definition->getKey('id');

      // Querying once again to validate the entities still exist.
      $ids = $storage
        ->getQuery()
        ->condition($id_key, $data['batch'], 'IN')
        ->addMetaData('trash', 'inactive')
        ->accessCheck(FALSE)
        ->execute();

      if ($ids) {
        $this->trashManager->executeInTrashContext('inactive', function () use ($storage, $ids) {
          $storage->delete($storage->loadMultiple($ids));
        });
        $message = $this->formatPlural(count($ids), 'Successfully purged @count @item', 'Successfully purged @count @items', [
          '@count' => count($ids),
          '@item' => $entity_definition->getSingularLabel(),
          '@items' => $entity_definition->getPluralLabel(),
        ]);

        $this->loggerFactory->get('trash')->info($message);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('trash')->error("An error prevented purging local items. Error message: {$e->getMessage()}");
    }
  }

}
