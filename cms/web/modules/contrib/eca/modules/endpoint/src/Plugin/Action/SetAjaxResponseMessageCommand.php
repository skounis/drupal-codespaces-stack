<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Add a message to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_message",
 *   label = @Translation("Ajax Response: set message"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseMessageCommand extends ResponseAjaxCommandBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $message = (string) $this->tokenService->replaceClear($this->configuration['message']);
    $wrapper = (string) $this->tokenService->replaceClear($this->configuration['wrapper']);
    if ($wrapper === '') {
      $wrapper = NULL;
    }
    $type = $this->configuration['type'];
    if ($type === '_eca_token') {
      $type = $this->getTokenValue('type', 'status');
    }
    $options = [
      'type' => $type,
    ];
    $id = (string) $this->tokenService->replaceClear($this->configuration['id']);
    if ($id !== '') {
      $options['id'] = $id;
    }
    $announce = (string) $this->tokenService->replaceClear($this->configuration['announce']);
    if ($announce !== '') {
      $options['announce'] = $announce;
      $options['priority'] = (string) $this->tokenService->replaceClear($this->configuration['priority']);
    }
    $clear = $this->configuration['clear'];
    return new MessageCommand($message, $wrapper, $options, $clear);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'message' => '',
      'wrapper' => '',
      'id' => '',
      'type' => 'status',
      'announce' => '',
      'priority' => '',
      'clear' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#description' => $this->t('The message type.'),
      '#options' => [
        'status' => $this->t('Status'),
        'warning' => $this->t('Warning'),
        'error' => $this->t('Error'),
      ],
      '#default_value' => $this->configuration['type'],
      '#weight' => -50,
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#description' => $this->t('The message returned to the ajax response.'),
      '#default_value' => $this->configuration['message'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['wrapper'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrapper Selector'),
      '#description' => $this->t('The CSS selector for the wrapper for messages. Leave empty to use defaults.'),
      '#default_value' => $this->configuration['wrapper'],
      '#weight' => -40,
      '#eca_token_replacement' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#description' => $this->t('The message ID, it can be a simple value or several values separated by a space which can be used as an explicit selector for a message.'),
      '#default_value' => $this->configuration['id'],
      '#weight' => -35,
      '#eca_token_replacement' => TRUE,
    ];
    $form['announce'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Announce'),
      '#description' => $this->t('The CSS selector for the wrapper for messages. Leave empty to use defaults.'),
      '#default_value' => $this->configuration['announce'],
      '#weight' => -30,
      '#eca_token_replacement' => TRUE,
    ];
    $form['priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Announce priority'),
      '#description' => $this->t('The priority that will be used for the announcement. Defaults to empty/unset which will not set a priority in the response sent to the client and therefore the default of polite will be used for the message.'),
      '#default_value' => $this->configuration['priority'],
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    $form['clear'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear previous messages'),
      '#description' => $this->t('If TRUE, previous messages will be cleared first.'),
      '#default_value' => $this->configuration['clear'],
      '#weight' => -20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = (string) $form_state->getValue('message');
    $this->configuration['wrapper'] = (string) $form_state->getValue('wrapper');
    $this->configuration['id'] = (string) $form_state->getValue('id');
    $this->configuration['type'] = (string) $form_state->getValue('type');
    $this->configuration['announce'] = (string) $form_state->getValue('announce');
    $this->configuration['priority'] = (string) $form_state->getValue('priority');
    $this->configuration['clear'] = (string) $form_state->getValue('clear');
    parent::submitConfigurationForm($form, $form_state);
  }

}
