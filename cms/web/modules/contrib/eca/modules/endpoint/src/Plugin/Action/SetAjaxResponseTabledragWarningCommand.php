<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\TabledragWarningCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a table drag warning to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_tabledrag_warning",
 *   label = @Translation("Ajax Response: tabledrag warning"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseTabledragWarningCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $id = (string) $this->tokenService->replaceClear($this->configuration['id']);
    $instance = (string) $this->tokenService->replaceClear($this->configuration['instance']);
    return new TabledragWarningCommand($id, $instance);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'id' => '',
      'instance' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#description' => $this->t('The id of the changed row.'),
      '#default_value' => $this->configuration['id'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table instance'),
      '#description' => $this->t('The identifier of the tabledrag instance.'),
      '#default_value' => $this->configuration['instance'],
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
    $this->configuration['id'] = (string) $form_state->getValue('id');
    $this->configuration['instance'] = (string) $form_state->getValue('instance');
    parent::submitConfigurationForm($form, $form_state);
  }

}
