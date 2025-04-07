<?php

namespace Drupal\search_api_autocomplete;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides permissions of the search_api_autocomplete module.
 */
class Permissions implements ContainerInjectionInterface {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * The entity storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a Permissions object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage service.
   */
  public function __construct(EntityStorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('search_api_autocomplete_search')
    );
  }

  /**
   * Returns a list of permissions, one per configured search.
   *
   * @return array[]
   *   A list of permission definitions, keyed by permission machine name.
   */
  public function bySearch() {
    return $this->generatePermissions($this->storage->loadMultiple(), [$this, 'buildPermissions']);
  }

  /**
   * Returns a list of permissions for a single configured search.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  public function buildPermissions(SearchInterface $search) {
    $perms = [];
    $perms['use search_api_autocomplete for ' . $search->id()] = [
      'title' => $this->t('Use autocomplete for the %search search', ['%search' => $search->label()]),
    ];
    return $perms;
  }

}
