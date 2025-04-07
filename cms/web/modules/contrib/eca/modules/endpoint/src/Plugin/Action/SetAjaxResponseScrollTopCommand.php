<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\ScrollTopCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a scroll to top command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_scroll_top",
 *   label = @Translation("Ajax Response: scroll to top"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseScrollTopCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    return new ScrollTopCommand($selector);
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
      '#title' => $this->t('CSS Selector'),
      '#description' => $this->t('A CSS selector.'),
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
