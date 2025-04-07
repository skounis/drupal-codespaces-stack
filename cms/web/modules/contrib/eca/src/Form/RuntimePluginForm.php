<?php

namespace Drupal\eca\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Form class for validating a plugin.
 *
 * This class solely exists for ECA being able to validate a plugin on runtime,
 * using validation mechanics of the Form API.
 *
 * @see \Drupal\eca\Entity\Eca::validatePlugin
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
final class RuntimePluginForm implements FormInterface {

  /**
   * The plugin.
   *
   * @var \Drupal\Core\Plugin\PluginFormInterface
   */
  protected PluginFormInterface $plugin;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eca_runtime_plugin_form';
  }

  /**
   * Constructs a new RuntimePluginForm object.
   */
  public function __construct(PluginFormInterface $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $form['configuration'] = [];
    $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
    $form['configuration'] = $this->plugin->buildConfigurationForm($form['configuration'], $subform_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
    $this->plugin->validateConfigurationForm($form['configuration'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
    $this->plugin->submitConfigurationForm($form['configuration'], $subform_state);
  }

}
