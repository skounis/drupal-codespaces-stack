<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\SetDialogOptionCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a set dialog option command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_set_dialog_option",
 *   label = @Translation("Ajax Response: set dialog option"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseSetDialogOptionCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    $name = (string) $this->tokenService->replaceClear($this->configuration['name']);
    $value = (string) $this->tokenService->replaceClear($this->configuration['value']);
    return new SetDialogOptionCommand($selector, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'name' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selector'),
      '#description' => $this->t('The selector of the dialog whose title will be set. If set to an empty value, the default modal dialog will be selected.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
      '#eca_token_replacement' => TRUE,
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The name of the option to set. May be any jQuery UI dialog option. See https://api.jqueryui.com/dialog.'),
      '#default_value' => $this->configuration['name'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The value of the option to be passed to the dialog.'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -35,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    $this->configuration['name'] = (string) $form_state->getValue('name');
    $this->configuration['value'] = (string) $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
