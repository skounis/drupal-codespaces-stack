<?php

namespace Drupal\easy_email_override\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Email override entities.
 */
interface EmailOverrideInterface extends ConfigEntityInterface {

  /**
   * @return string
   */
  public function getId();

  /**
   * @param string $id
   *
   * @return EmailOverride
   */
  public function setId($id);

  /**
   * @return string
   */
  public function getLabel();

  /**
   * @param string $label
   *
   * @return EmailOverride
   */
  public function setLabel($label);

  /**
   * @return array
   */
  public function getParamMap();

  /**
   * @param array $paramMap
   *
   * @return EmailOverride
   */
  public function setParamMap($paramMap);

  /**
   * @return string
   */
  public function getModule();

  /**
   * @param string $module
   *
   * @return EmailOverride
   */
  public function setModule($module);

  /**
   * @return string
   */
  public function getKey();

  /**
   * @param string $key
   *
   * @return EmailOverride
   */
  public function setKey($key);

  /**
   * @return string
   */
  public function getEasyEmailType();

  /**
   * @param string $easy_email_type
   */
  public function setEasyEmailType($easy_email_type);

  /**
   * @return array
   */
  public function getCopiedFields();

  /**
   * @param array $copied_fields
   *
   * @return EmailOverride
   */
  public function setCopiedFields($copied_fields);

}
