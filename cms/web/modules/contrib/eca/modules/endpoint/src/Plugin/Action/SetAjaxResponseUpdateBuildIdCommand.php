<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add an update build id command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_update_build_id",
 *   label = @Translation("Ajax Response: update build id"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseUpdateBuildIdCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $old = (string) $this->tokenService->replaceClear($this->configuration['old']);
    $new = (string) $this->tokenService->replaceClear($this->configuration['new']);
    return new UpdateBuildIdCommand($old, $new);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'old' => '',
      'new' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['old_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Old ID'),
      '#description' => $this->t('The old build ID.'),
      '#default_value' => $this->configuration['old'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['new_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New ID'),
      '#description' => $this->t('The new build ID.'),
      '#default_value' => $this->configuration['new'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['old'] = (string) $form_state->getValue('old_id');
    $this->configuration['new'] = (string) $form_state->getValue('new_id');
    parent::submitConfigurationForm($form, $form_state);
  }

}
