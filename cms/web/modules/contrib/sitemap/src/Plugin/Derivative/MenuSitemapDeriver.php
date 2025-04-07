<?php

namespace Drupal\sitemap\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class MenuSitemapDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $menuStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->menuStorage = $container->get('entity_type.manager')->getStorage('menu');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->menuStorage->loadMultiple() as $menu => $entity) {
      /** @var \Drupal\system\Entity\Menu $entity */
      $this->derivatives[$menu] = $base_plugin_definition;
      $this->derivatives[$menu]['title'] = $this->t('Menu: @menu', ['@menu' => $entity->label()]);
      $this->derivatives[$menu]['description'] = $entity->getDescription();
      $this->derivatives[$menu]['settings']['title'] = NULL;
      $this->derivatives[$menu]['menu'] = $entity->id();
      $this->derivatives[$menu]['config_dependencies']['config'] = [$entity->getConfigDependencyName()];
    }
    return $this->derivatives;
  }

}
