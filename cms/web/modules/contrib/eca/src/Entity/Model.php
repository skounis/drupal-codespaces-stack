<?php

namespace Drupal\eca\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\eca\Plugin\ECA\Modeller\ModellerInterface;

/**
 * Defines the ECA Model entity type.
 *
 * @ConfigEntityType(
 *   id = "eca_model",
 *   label = @Translation("ECA Model"),
 *   label_collection = @Translation("ECA Models"),
 *   label_singular = @Translation("ECA Model"),
 *   label_plural = @Translation("ECA Models"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ECA Model",
 *     plural = "@count ECA Models",
 *   ),
 *   config_prefix = "model",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "tags",
 *     "documentation",
 *     "filename",
 *     "modeldata"
 *   }
 * )
 */
class Model extends ConfigEntityBase {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    /** @var \Drupal\eca\Entity\Eca|null $eca */
    $eca = $this->entityTypeManager()->getStorage('eca')->load($this->id());
    if ($eca) {
      $this->addDependency('config', $eca->getConfigDependencyName());
    }

    return $this;
  }

  /**
   * Set the filename or raw data of the model by the modeller.
   *
   * @param \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface $modeller
   *   The modeller instance which handles the model and can provide either the
   *   filename or the raw data to be stored.
   *
   * @return $this
   */
  public function setData(ModellerInterface $modeller): Model {
    $this
      ->setLabel($modeller->getLabel())
      ->setTags($modeller->getTags())
      ->setDocumentation($modeller->getDocumentation())
      ->setFilename($modeller->getFilename())
      ->setModeldata($modeller->getModeldata());
    return $this;
  }

  /**
   * Set the label of this model.
   *
   * @return $this
   */
  public function setLabel(string $label): Model {
    $this->set('label', $label);
    return $this;
  }

  /**
   * Set the tags of this model.
   *
   * @return $this
   */
  public function setTags(array $tags): Model {
    $this->set('tags', empty($tags) ? ['untagged'] : $tags);
    return $this;
  }

  /**
   * Get the tags of this model.
   *
   * @return array
   *   The tags of this model.
   */
  public function getTags(): array {
    return $this->get('tags') ?? [];
  }

  /**
   * Set the documentation of this model.
   *
   * @return $this
   */
  public function setDocumentation(string $documentation): Model {
    $this->set('documentation', $documentation);
    return $this;
  }

  /**
   * Get the documentation of this model.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(): string {
    return $this->get('documentation') ?? '';
  }

  /**
   * Set the external filename of this model.
   *
   * @return $this
   */
  public function setFilename(string $filename): Model {
    $this->set('filename', $filename);
    return $this;
  }

  /**
   * Get the external filename of this model.
   *
   * @return string
   *   The external filename.
   */
  public function getFilename(): string {
    return $this->get('filename') ?? '';
  }

  /**
   * Set the external filename of this model.
   *
   * @return $this
   */
  public function setModeldata(string $modeldata): Model {
    $this->set('modeldata', $modeldata);
    return $this;
  }

  /**
   * Get the raw model data of this model.
   *
   * @return string
   *   The raw model data.
   */
  public function getModeldata(): string {
    return $this->get('modeldata') ?? '';
  }

}
