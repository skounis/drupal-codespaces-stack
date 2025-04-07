<?php

namespace Drupal\ai_agents\Output;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The structured result data object.
 */
class StructuredResultData implements StructuredResultDataInterface {

  /**
   * The created configs.
   *
   * @var array
   *   The created configs.
   */
  protected array $createdConfigs = [];

  /**
   * The edited configs.
   *
   * @var array
   *   The edited configs.
   */
  protected array $editedConfigs = [];

  /**
   * The deleted configs.
   *
   * @var array
   *   The deleted configs.
   */
  protected array $deletedConfigs = [];

  /**
   * The created entities.
   *
   * @var array
   *   The created entities.
   */
  protected array $createdEntities = [];

  /**
   * The edited entities.
   *
   * @var array
   *   The edited entities.
   */
  protected array $editedEntities = [];

  /**
   * The deleted entities.
   *
   * @var array
   *   The deleted entities.
   */
  protected array $deletedEntities = [];

  /**
   * {@inheritdoc}
   */
  public function getCreatedConfigs(): array {
    return $this->createdConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedConfigs(array $createdConfigs): void {
    $this->createdConfigs = $createdConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedConfig(ConfigEntityInterface $createdConfig, array $extraData = []): void {
    // If the config is a config entity, create a id.
    $createdConfigId = $this->createConfigId($createdConfig);
    $this->createdConfigs[$createdConfigId] = [
      'config_id' => $createdConfigId,
    ];
    if (!empty($extraData)) {
      $this->createdConfigs[$createdConfigId]['extra_data'] = $extraData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEditedConfigs(): array {
    return $this->editedConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function setEditedConfigs(array $editedConfigs): void {
    $this->editedConfigs = $editedConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function setEditedConfig(ConfigEntityInterface $editedConfig, array $extraData = []): void {
    // If the config is a config entity, create a id.
    $editedConfigId = $this->createConfigId($editedConfig);
    $this->editedConfigs[$editedConfigId] = [
      'config_id' => $editedConfigId,
    ];
    if (!empty($extraData)) {
      $this->editedConfigs[$editedConfigId]['extra_data'] = $extraData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDeletedConfigs(): array {
    return $this->deletedConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeletedConfigs(array $deletedConfigs): void {
    $this->deletedConfigs = $deletedConfigs;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeletedConfig(ConfigEntityInterface $deletedConfig, array $extraData = []): void {
    // If the config is a config entity, create a id.
    $deletedConfigId = $this->createConfigId($deletedConfig);
    $this->deletedConfigs[$deletedConfigId] = [
      'config_id' => $deletedConfigId,
    ];
    if (!empty($extraData)) {
      $this->deletedConfigs[$deletedConfigId]['extra_data'] = $extraData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedContents(): array {
    return $this->createdEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedContents(array $createdEntities): void {
    $this->createdEntities = $createdEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedContent(ContentEntityInterface $createdEntity, array $extraData = []): void {
    $createdEntityKey = $this->createEntityId($createdEntity);
    $this->createdEntities[$createdEntityKey] = [
      'entity_key' => $createdEntityKey,
      'label' => method_exists($createdEntity, 'label') ? $createdEntity->label() : $createdEntity->id(),
    ];
    if (!empty($extraData)) {
      $this->createdEntities[$createdEntityKey]['extra_data'] = $extraData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEditedContents(): array {
    return $this->editedEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setEditedContents(array $editedEntities): void {
    $this->editedEntities = $editedEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setEditedContent(ContentEntityInterface $editedEntity, array $extraData = []): void {
    // If the entity is a content entity, create a id.
    $editedEntityKey = $this->createEntityId($editedEntity);

    $this->editedEntities[$editedEntityKey] = [
      'entity_key' => $editedEntityKey,
      'label' => method_exists($editedEntity, 'label') ? $editedEntity->label() : $editedEntity->id(),
    ];
    if (!empty($extraData)) {
      $this->createdEntities[$editedEntityKey]['extra_data'] = $extraData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDeletedContents(): array {
    return $this->deletedEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeletedContents(array $deletedEntities): void {
    $this->deletedEntities = $deletedEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeletedContent(ContentEntityInterface $deletedEntity, array $extraData = []): void {
    // If the entity is a content entity, create a id.
    $deletedEntityKey = $this->createEntityId($deletedEntity);

    $this->deletedEntities[$deletedEntityKey] = [
      'entity_key' => $deletedEntityKey,
      'label' => method_exists($deletedEntity, 'label') ? $deletedEntity->label() : $deletedEntity->id(),
    ];
    if (!empty($extraData)) {
      $this->createdEntities[$deletedEntityKey]['extra_data'] = $extraData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $output = [];
    if (count($this->createdConfigs) > 0) {
      $output['created_configs'] = array_values($this->createdConfigs);
    }
    if (count($this->editedConfigs) > 0) {
      $output['edited_configs'] = array_values($this->editedConfigs);
    }
    if (count($this->deletedConfigs) > 0) {
      $output['deleted_configs'] = array_values($this->deletedConfigs);
    }
    if (count($this->createdEntities) > 0) {
      $output['created_entities'] = array_values($this->createdEntities);
    }
    if (count($this->editedEntities) > 0) {
      $output['edited_entities'] = array_values($this->editedEntities);
    }
    if (count($this->deletedEntities) > 0) {
      $output['deleted_entities'] = array_values($this->deletedEntities);
    }
    return $output;
  }

  /**
   * Get the configuration id from a config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $config
   *   The config entity.
   *
   * @return string
   *   The configuration id.
   */
  protected function createConfigId(ConfigEntityInterface $config): string {
    return $config->getEntityTypeId() . ':' . $config->id();
  }

  /**
   * Helper function to create unique id for content entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The unique id.
   */
  protected function createEntityId(ContentEntityInterface $entity): string {
    // Create a unique key from the entity type, id and language.
    $lang = $entity->language() ? $entity->language()->getId() : 'und';
    return $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $lang;
  }

}
