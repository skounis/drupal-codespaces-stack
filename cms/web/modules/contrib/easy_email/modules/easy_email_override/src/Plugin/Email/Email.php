<?php

namespace Drupal\easy_email_override\Plugin\Email;

use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a plugin class for declared emails
 */
class Email extends PluginBase implements EmailInterface {

  public function getId() {
    return $this->pluginDefinition['id'];
  }

  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  public function getModule() {
    return $this->pluginDefinition['module'];
  }

  public function getKey() {
    return $this->pluginDefinition['key'];
  }

  public function getParams() {
    return $this->pluginDefinition['params'];
  }

}