<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add an announcement to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_announce",
 *   label = @Translation("Ajax Response: set announcement"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseAnnounceCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $message = (string) $this->tokenService->replaceClear($this->configuration['message']);
    $priority = (string) $this->tokenService->replaceClear($this->configuration['priority']);
    if ($priority === '') {
      $priority = NULL;
    }
    return new AnnounceCommand($message, $priority);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'message' => '',
      'priority' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#description' => $this->t('The message returned to the ajax response.'),
      '#default_value' => $this->configuration['message'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Announce priority'),
      '#description' => $this->t('The priority that will be used for the announcement. Defaults to empty/unset which will not set a priority in the response sent to the client and therefore the default of polite will be used for the message.'),
      '#default_value' => $this->configuration['priority'],
      '#weight' => -40,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = (string) $form_state->getValue('message');
    $this->configuration['priority'] = (string) $form_state->getValue('priority');
    parent::submitConfigurationForm($form, $form_state);
  }

}
