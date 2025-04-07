<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\trash\EntityQuery\Sql\PgsqlQueryFactory as CorePgsqlQueryFactory;
use Drupal\trash\EntityQuery\Sql\QueryFactory as CoreQueryFactory;
use Drupal\trash\EntityQuery\Workspaces\PgsqlQueryFactory as WorkspacesPgsqlQueryFactory;
use Drupal\trash\EntityQuery\Workspaces\QueryFactory as WorkspacesQueryFactory;
use Drupal\trash\Handler\TrashHandlerPass;
use Drupal\trash\LayoutBuilder\TrashInlineBlockUsage;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters container services.
 */
class TrashServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new TrashHandlerPass());
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // @todo Revert to decorating the entity type manager when we can require
    //   Drupal 10.
    if ($container->hasDefinition('entity_type.manager')) {
      $container->getDefinition('entity_type.manager')
        ->setClass(TrashEntityTypeManager::class);
    }

    // Decorate entity query factories.
    if ($container->hasDefinition('workspaces.entity.query.sql')) {
      $factory = [
        'service' => 'workspaces.entity.query.sql',
        'class' => WorkspacesQueryFactory::class,
      ];
      $pgsql_factory = [
        'service' => 'pgsql.workspaces.entity.query.sql',
        'class' => WorkspacesPgsqlQueryFactory::class,
      ];
    }
    else {
      $factory = [
        'service' => 'entity.query.sql',
        'class' => CoreQueryFactory::class,
      ];
      $pgsql_factory = [
        'service' => 'pgsql.entity.query.sql',
        'class' => CorePgsqlQueryFactory::class,
      ];
    }
    $definition = (new ChildDefinition($factory['service']))
      ->setClass($factory['class'])
      ->setDecoratedService('entity.query.sql', NULL, 100);
    $container->setDefinition('trash.entity.query.sql', $definition);
    $definition = (new ChildDefinition($pgsql_factory['service']))
      ->setClass($pgsql_factory['class'])
      ->setDecoratedService('pgsql.entity.query.sql', NULL, 100);
    $container->setDefinition('trash.pgsql.entity.query.sql', $definition);

    if ($container->hasDefinition('workspaces.information')) {
      $container->register('trash.workspaces.information', TrashWorkspaceInformation::class)
        ->setPublic(FALSE)
        ->setDecoratedService('workspaces.information', NULL, 100)
        ->addArgument(new Reference('trash.workspaces.information.inner'))
        ->addArgument(new Reference('trash.manager'));
    }

    if ($container->hasDefinition('workspaces.manager')) {
      $container->register('trash.workspaces.manager', TrashWorkspaceManager::class)
        ->setPublic(FALSE)
        ->setDecoratedService('workspaces.manager', NULL, 100)
        ->addArgument(new Reference('trash.workspaces.manager.inner'))
        ->addArgument(new Reference('trash.manager'));
    }

    if ($container->hasDefinition('inline_block.usage')) {
      $container->register('trash.inline_block.usage', TrashInlineBlockUsage::class)
        ->setPublic(FALSE)
        ->setDecoratedService('inline_block.usage')
        ->addArgument(new Reference('trash.inline_block.usage.inner'))
        ->addArgument(new Reference('trash.manager'));
    }

    if ($container->hasDefinition('wse_menu.tree_storage')) {
      $container->getDefinition('wse_menu.tree_storage')
        ->setClass(TrashWseMenuTreeStorage::class)
        ->addMethodCall('setTrashManager', [new Reference('trash.manager')]);
    }
  }

}
