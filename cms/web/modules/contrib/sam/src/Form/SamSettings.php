<?php

namespace Drupal\sam\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class to create and submit form with module settings options.
 */
class SamSettings extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'sam_admin_settings';
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'sam.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sam.settings');
    $form['add_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("'Add more' button label"),
      '#default_value' => $config->get('add_more_label') ?? $this->t('Add another item'),
      '#required' => TRUE,
    ];
    $form['remove_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("'Remove' button label"),
      '#default_value' => $config->get('remove_label') ?? $this->t('Remove'),
      '#required' => TRUE,
    ];

    $form['help_text_singular'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remaining items help text - Singular'),
      '#default_value' => $config->get('help_text_singular') ?? $this->t('@count additional item can be added'),
      '#description' => $this->t('A help text indicating one last single item to reveal. The placeholder "@count" can be used to indicate that number.'),
      '#required' => TRUE,
    ];

    $form['help_text_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remaining items help text - Plural'),
      '#default_value' => $config->get('help_text_plural') ?? $this->t('@count additional items can be added'),
      '#description' => $this->t('A help text indicating how many items are left to reveal. The placeholder "@count" can be used to indicate the number of items remaining.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $text_field_button = $form_state->getValue('add_more_label');
    $remove_button = $form_state->getValue('remove_label');
    $text_helper_singular = $form_state->getValue('help_text_singular');
    $text_helper_plural = $form_state->getValue('help_text_plural');
    $this->config('sam.settings')
      ->set('add_more_label', $text_field_button);
    $this->config('sam.settings')
      ->set('remove_label', $remove_button);
    $this->config('sam.settings')
      ->set('help_text_singular', $text_helper_singular);
    $this->config('sam.settings')
      ->set('help_text_plural', $text_helper_plural);
    $this->config('sam.settings')->save();
    parent::submitForm($form, $form_state);
  }

}
