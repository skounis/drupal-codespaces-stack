<?php

namespace Drupal\klaro;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface definition for a Klaro! purpose config entity.
 */
interface KlaroPurposeInterface extends ConfigEntityInterface {

  /**
   * Getter for the id.
   *
   * @return string|null
   *   The Id.
   */
  public function id(): ?string;

  /**
   * Setter for the id.
   *
   * @param string $id
   *   The id.
   *
   * @return \Drupal\klaro\KlaroPurposeInterface
   *   The instance.
   */
  public function setId(string $id): KlaroPurposeInterface;

  /**
   * Getter for the label.
   *
   * @return string|null
   *   The label.
   */
  public function label(): ?string;

  /**
   * Setter for the label.
   *
   * @param string $label
   *   The label.
   *
   * @return \Drupal\klaro\KlaroPurposeInterface
   *   The instance.
   */
  public function setLabel(string $label): KlaroPurposeInterface;

  /**
   * Getter for the weight.
   *
   * @return int
   *   The weight.
   */
  public function weight(): int;

  /**
   * Setter for the weight.
   *
   * @param int $weight
   *   The weight.
   *
   * @return \Drupal\klaro\KlaroPurposeInterface
   *   The instance.
   */
  public function setWeight(int $weight = 0): KlaroPurposeInterface;

}
