<?php

namespace Drupal\geocoder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The geocoder settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geocoder_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['geocoder.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('geocoder.settings');
    // Checking the typedConfigManager - property exists in ConfigFormBase.
    if (property_exists(ConfigFormBase::class, 'typedConfigManager')) {
      $geocoder_config_schema = $this->typedConfigManager->getDefinition('geocoder.settings') + ['mapping' => []];
    }
    else {
      // @phpstan-ignore-next-line as Handling backward compatibility before D10.2.
      $typedConfigManager = \Drupal::service('config.typed');
      $geocoder_config_schema = $typedConfigManager->getDefinition('geocoder.settings') + ['mapping' => []];
    }

    $geocoder_config_schema = $geocoder_config_schema['mapping'];

    // Attach Geofield Map Library.
    $form['#attached']['library'] = [
      'geocoder/general',
    ];

    $form['geocoder_presave_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $geocoder_config_schema['geocoder_presave_disabled']['label'],
      '#description' => $geocoder_config_schema['geocoder_presave_disabled']['description'],
      '#default_value' => $config->get('geocoder_presave_disabled'),
    ];

    $form['cache'] = [
      '#type' => 'checkbox',
      '#title' => $geocoder_config_schema['cache']['label'],
      '#description' => $geocoder_config_schema['cache']['description'],
      '#default_value' => $config->get('cache'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get all the form state values, in an array structure.
    $form_state_values = $form_state->getValues();

    $config = $this->config('geocoder.settings');
    $config->set('geocoder_presave_disabled', $form_state_values['geocoder_presave_disabled']);
    $config->set('cache', $form_state_values['cache']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
