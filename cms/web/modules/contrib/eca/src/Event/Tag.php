<?php

namespace Drupal\eca\Event;

/**
 * Class containing tag constants for Event characterization.
 */
final class Tag {

  /**
   * Related to a data read operation.
   */
  const READ = 0b00000000001;

  /**
   * Related to a data write operation.
   */
  const WRITE = 0b00000000010;

  /**
   * Related to a view operation.
   */
  const VIEW = 0b00000000100;

  /**
   * Related to an operation based on the runtime memory.
   */
  const RUNTIME = 0b00000001000;

  /**
   * Related to an operation based on an ephemeral storage.
   */
  const EPHEMERAL = 0b00000010000;

  /**
   * Related to an operation based on a persistent storage.
   */
  const PERSISTENT = 0b00000100000;

  /**
   * Instantiated before a scoped operation.
   */
  const BEFORE = 0b00001000000;

  /**
   * Instantiated after a scoped operation.
   */
  const AFTER = 0b00010000000;

  /**
   * Related operation always produces the same result if context is the same.
   */
  const IDEMPOTENT = 0b00100000000;

  /**
   * Related operation relates to content.
   */
  const CONTENT = 0b01000000000;

  /**
   * Related operation relates to config.
   */
  const CONFIG = 0b10000000000;

  /**
   * Get a list of all available tags.
   *
   * @return array
   *   An array keyed by tag constants, and the values are translated labels.
   */
  public static function getTags() {
    return [
      self::READ => t("Read"),
      self::WRITE => t("Write"),
      self::VIEW => t("View"),
      self::RUNTIME => t("Runtime memory"),
      self::EPHEMERAL => t("Ephemeral storage"),
      self::PERSISTENT => t("Persistent storage"),
      self::BEFORE => t("Happens before"),
      self::AFTER => t("Happens after"),
      self::IDEMPOTENT => t("Idempotent"),
      self::CONTENT => t("Content-related"),
      self::CONFIG => t("Config-related"),
    ];
  }

}
