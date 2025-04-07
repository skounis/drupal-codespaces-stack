<?php

namespace Drupal\eca\Plugin\Action;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Base class for configurable ECA action plugins.
 */
abstract class ConfigurableActionBase extends ActionBase implements ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  use ConfigurableActionTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

}
