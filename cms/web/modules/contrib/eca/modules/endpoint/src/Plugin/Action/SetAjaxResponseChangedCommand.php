<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a changed command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_changed",
 *   label = @Translation("Ajax Response: set changed"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseChangedCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    $asterisk = (string) $this->tokenService->replaceClear($this->configuration['asterisk']);
    return new ChangedCommand($selector, $asterisk);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'asterisk' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS Selector'),
      '#description' => $this->t('CSS selector for elements to be marked as changed.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['asterisk'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asterisk'),
      '#description' => $this->t('CSS selector for elements to which an asterisk will be appended.'),
      '#default_value' => $this->configuration['asterisk'],
      '#weight' => -40,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    $this->configuration['asterisk'] = (string) $form_state->getValue('asterisk');
    parent::submitConfigurationForm($form, $form_state);
  }

}
