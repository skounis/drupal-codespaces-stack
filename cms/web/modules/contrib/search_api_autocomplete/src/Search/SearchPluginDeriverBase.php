<?php

namespace Drupal\search_api_autocomplete\Search;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for search plugin derivers.
 */
abstract class SearchPluginDeriverBase extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Existing instances of this class.
   *
   * @var \Drupal\search_api_autocomplete\Search\SearchPluginDeriverBase[][]
   */
  protected static $instances = [];

  /**
   * {@inheritdoc}
   */
  protected $derivatives = NULL;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $deriver = new static();

    $deriver->setEntityTypeManager($container->get('entity_type.manager'));
    $deriver->setStringTranslation($container->get('string_translation'));

    static::$instances[$base_plugin_id][] = $deriver;

    return $deriver;
  }

  /**
   * Resets the statically cached derivatives for all instances of this class.
   *
   * @param string|null $base_plugin_id
   *   (optional) If given, only reset the caches on derivers for the given base
   *   plugin ID.
   */
  public static function resetStaticDerivativeCaches($base_plugin_id = NULL) {
    $instances = static::$instances;
    if ($base_plugin_id) {
      $instances = !empty($instances[$base_plugin_id]) ? $instances[$base_plugin_id] : [];
    }
    foreach ($instances as $deriver) {
      $deriver->derivatives = NULL;
    }
  }

  /**
   * Retrieves the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Sets the entity manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

}
