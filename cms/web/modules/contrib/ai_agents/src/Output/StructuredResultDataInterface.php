<?php

namespace Drupal\ai_agents\Output;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The structured result data object.
 */
interface StructuredResultDataInterface {

  /**
   * Get the created configs.
   *
   * @return array
   *   The created configs.
   */
  public function getCreatedConfigs(): array;

  /**
   * Set the created configs.
   *
   * @param array $createdConfigs
   *   The created configs.
   */
  public function setCreatedConfigs(array $createdConfigs): void;

  /**
   * Set one created config.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $createdConfig
   *   The created config.
   * @param array $extraData
   *   Extra data to store.
   */
  public function setCreatedConfig(ConfigEntityInterface $createdConfig, array $extraData = []): void;

  /**
   * Get the edited configs.
   *
   * @return array
   *   The edited configs.
   */
  public function getEditedConfigs(): array;

  /**
   * Set the edited configs.
   *
   * @param array $editedConfigs
   *   The edited configs.
   */
  public function setEditedConfigs(array $editedConfigs): void;

  /**
   * Set one edited config.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $editedConfig
   *   The edited config.
   * @param array $extraData
   *   Extra data to store.
   */
  public function setEditedConfig(ConfigEntityInterface $editedConfig, array $extraData = []): void;

  /**
   * Get the deleted configs.
   *
   * @return array
   *   The deleted configs.
   */
  public function getDeletedConfigs(): array;

  /**
   * Set the deleted configs.
   *
   * @param array $deletedConfigs
   *   The deleted configs.
   */
  public function setDeletedConfigs(array $deletedConfigs): void;

  /**
   * Set one deleted config.
   *
   * @param string|\Drupal\Core\Config\Entity\ConfigEntityInterface $deletedConfig
   *   The deleted config.
   * @param array $extraData
   *   Extra data to store.
   */
  public function setDeletedConfig(ConfigEntityInterface $deletedConfig, array $extraData = []): void;

  /**
   * Get the created entities.
   *
   * @return array
   *   The created entities.
   */
  public function getCreatedContents(): array;

  /**
   * Set the created entities.
   *
   * @param array $createdEntities
   *   The created entities.
   */
  public function setCreatedContents(array $createdEntities): void;

  /**
   * Set one created entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $createdEntity
   *   The created entity.
   * @param array $extraData
   *   Extra data to store.
   */
  public function setCreatedContent(ContentEntityInterface $createdEntity, array $extraData = []): void;

  /**
   * Get the edited entities.
   *
   * @return array
   *   The edited entities.
   */
  public function getEditedContents(): array;

  /**
   * Set the edited entities.
   *
   * @param array $editedEntities
   *   The edited entities.
   */
  public function setEditedContents(array $editedEntities): void;

  /**
   * Set one edited entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $editedEntity
   *   The edited entity.
   * @param array $extraData
   *   Extra data to store.
   */
  public function setEditedContent(ContentEntityInterface $editedEntity, array $extraData = []): void;

  /**
   * Get the deleted entities.
   *
   * @return array
   *   The deleted entities.
   */
  public function getDeletedContents(): array;

  /**
   * Set the deleted entities.
   *
   * @param array $deletedEntities
   *   The deleted entities.
   */
  public function setDeletedContents(array $deletedEntities): void;

  /**
   * Set one deleted entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $deletedEntity
   *   The deleted entity.
   * @param array $extraData
   *   Extra data to store.
   */
  public function setDeletedContent(ContentEntityInterface $deletedEntity, array $extraData = []): void;

  /**
   * Get the whole thing as an array.
   *
   * @return array
   *   The whole thing as an array.
   */
  public function toArray(): array;

}
