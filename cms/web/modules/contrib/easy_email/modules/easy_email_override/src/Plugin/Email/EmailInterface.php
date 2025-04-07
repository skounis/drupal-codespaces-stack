<?php
namespace Drupal\easy_email_override\Plugin\Email;

interface EmailInterface {

  /**
   * Gets the email ID.
   *
   * @return string
   *   The email ID.
   */
  public function getId();

  /**
   * Gets the translated label.
   *
   * @return string
   *   The translated label.
   */
  public function getLabel();

  /**
   * Gets the email module.
   *
   * @return string
   *   The module.
   */
  public function getModule();

  /**
   * Gets the email key.
   *
   * @return string
   *   The email key.
   */
  public function getKey();

  /**
   * Gets the email params.
   *
   * @return array
   *   The email params.
   */
  public function getParams();

}