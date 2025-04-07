<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a focus command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_focus_first",
 *   label = @Translation("Ajax Response: set focus"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseFocusFirstCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    return new FocusFirstCommand($selector);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selector'),
      '#description' => $this->t('A CSS selector of the container with tabbable elements.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
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
    parent::submitConfigurationForm($form, $form_state);
  }

}
