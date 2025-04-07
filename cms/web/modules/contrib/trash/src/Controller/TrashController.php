<?php

declare(strict_types=1);

namespace Drupal\trash\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\trash\TrashManagerInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to list deleted entities.
 */
class TrashController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    protected TrashManagerInterface $trashManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('trash.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('date.formatter')
    );
  }

  /**
   * Provides the trash listing page for any entity type.
   *
   * @param string|null $entity_type_id
   *   The ID of the entity type to render.
   *
   * @return array
   *   A render array.
   */
  public function listing(?string $entity_type_id = NULL) : array {
    $enabled_entity_types = $this->trashManager->getEnabledEntityTypes();
    if (empty($enabled_entity_types)) {
      throw new NotFoundHttpException();
    }

    $default_entity_type = in_array('node', $enabled_entity_types, TRUE) ? 'node' : reset($enabled_entity_types);
    $entity_type_id = $entity_type_id ?: $default_entity_type;
    if (!in_array($entity_type_id, $enabled_entity_types, TRUE)) {
      throw new NotFoundHttpException();
    }

    $build = $this->trashManager->executeInTrashContext('inactive', function () use ($entity_type_id) {
      return $this->render($entity_type_id);
    });
    $build['#cache']['tags'][] = 'config:trash.settings';

    return $build;
  }

  /**
   * Builds a listing of deleted entities for the given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  protected function render(string $entity_type_id): array {
    $options = [];
    foreach ($this->trashManager->getEnabledEntityTypes() as $id) {
      $options[$id] = (string) $this->entityTypeManager()->getDefinition($id)->getLabel();
    }
    $build['entity_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $options,
      '#sort_options' => TRUE,
      '#value' => $entity_type_id,
      '#attributes' => [
        'class' => ['trash-entity-type'],
      ],
      '#access' => (bool) $this->config('trash.settings')->get('compact_overview'),
    ];

    $entity_type = $this->entityTypeManager()->getDefinition($entity_type_id);
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader($entity_type),
      '#rows' => [],
      '#empty' => $this->t('There are no deleted @label.', ['@label' => $entity_type->getPluralLabel()]),
      '#cache' => [
        'contexts' => $entity_type->getListCacheContexts(),
        'tags' => $entity_type->getListCacheTags(),
      ],
    ];
    foreach ($this->load($entity_type) as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];
    $build['#attached']['library'][] = 'trash/trash.admin';

    return $build;
  }

  /**
   * Loads entities of this type from storage for listing.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entities implementing \Drupal\Core\Entity\EntityInterface
   *   indexed by their IDs.
   */
  protected function load(EntityTypeInterface $entity_type): array {
    $storage = $this->entityTypeManager()->getStorage($entity_type->id());
    $entity_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('deleted', 'DESC')
      ->pager(50)
      ->execute();
    return $storage->loadMultiple($entity_ids);
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   A render array structure of header strings.
   */
  protected function buildHeader(EntityTypeInterface $entity_type): array {
    $row['label'] = $this->t('Title');
    $row['bundle'] = $entity_type->getBundleLabel();
    if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
      $row['owner'] = $this->t('Author');
    }
    if ($entity_type->entityClassImplements(EntityPublishedInterface::class)) {
      $row['published'] = $this->t('Status');
    }
    if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
      $row['revision_user'] = $this->t('Deleted by');
    }
    $row['deleted'] = $this->t('Deleted on');
    $row['operations'] = $this->t('Operations');
    return $row;
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for this row of the list.
   *
   * @return array
   *   A render array structure of fields for this entity.
   */
  protected function buildRow(FieldableEntityInterface $entity): array {
    $entity_type = $entity->getEntityType();
    if ($entity_type->getLinkTemplate('canonical') != $entity_type->getLinkTemplate('edit-form') && $entity->access('view')) {
      $row['label']['data'] = [
        '#type' => 'link',
        '#title' => "{$entity->label()} ({$entity->id()})",
        '#url' => $entity->toUrl('canonical', ['query' => ['in_trash' => TRUE]]),
      ];
    }
    else {
      $row['label']['data'] = [
        '#markup' => "{$entity->label()} ({$entity->id()})",
      ];
    }

    $row['bundle'] = $this->bundleInfo->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'];

    if ($entity_type->entityClassImplements(EntityOwnerInterface::class)) {
      assert($entity instanceof EntityOwnerInterface);
      $row['owner']['data'] = [
        '#theme' => 'username',
        '#account' => $entity->getOwner(),
      ];
    }

    if ($entity_type->entityClassImplements(EntityPublishedInterface::class)) {
      assert($entity instanceof EntityPublishedInterface);
      $row['published'] = $entity->isPublished() ? $this->t('published') : $this->t('not published');
    }

    if ($entity_type->entityClassImplements(RevisionLogInterface::class)) {
      assert($entity instanceof RevisionLogInterface);
      $row['revision_user']['data'] = [
        '#theme' => 'username',
        '#account' => $entity->getRevisionUser(),
      ];
    }

    $row['deleted'] = $this->dateFormatter->format($entity->get('deleted')->value, 'short');

    $list_builder = $this->entityTypeManager->hasHandler($entity_type->id(), 'list_builder')
      ? $this->entityTypeManager->getListBuilder($entity_type->id())
      : $this->entityTypeManager->createHandlerInstance(EntityListBuilder::class, $entity_type);

    $row['operations']['data'] = [
      '#type' => 'operations',
      '#links' => $list_builder->getOperations($entity) ?? [],
      // Allow links to use modals.
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];

    return $row;
  }

}
