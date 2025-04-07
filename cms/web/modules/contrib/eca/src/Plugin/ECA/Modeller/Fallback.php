<?php

namespace Drupal\eca\Plugin\ECA\Modeller;

use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fallback plugin implementation of the ECA Modeller.
 *
 * @EcaModeller(
 *   id = "fallback",
 * )
 */
class Fallback extends ModellerBase {

  /**
   * {@inheritdoc}
   */
  public function generateId(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function createNewModel(string $id, string $model_data, ?string $filename = NULL, bool $save = FALSE): Eca {
    return $this->eca;
  }

  /**
   * {@inheritdoc}
   */
  public function save(string $data, ?string $filename = NULL, ?bool $status = NULL): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateModel(Model $model): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function enable(): ModellerInterface {
    $this->eca
      ->setStatus(TRUE)
      ->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(): ModellerInterface {
    $this->eca
      ->setStatus(FALSE)
      ->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(): ?Eca {
    return $this->eca;
  }

  /**
   * {@inheritdoc}
   */
  public function export(): ?Response {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setModeldata(string $data): ModellerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getModeldata(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(Eca $eca): ModellerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exportTemplates(): ModellerInterface {
    return $this;
  }

}
