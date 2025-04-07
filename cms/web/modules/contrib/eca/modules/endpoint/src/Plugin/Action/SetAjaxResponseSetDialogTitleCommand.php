<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\SetDialogTitleCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add a set dialog title command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_set_dialog_title",
 *   label = @Translation("Ajax Response: set dialog title"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseSetDialogTitleCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    $title = (string) $this->tokenService->replaceClear($this->configuration['title']);
    return new SetDialogTitleCommand($selector, $title);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'title' => '',
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
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The title that will be set on the dialog.'),
      '#default_value' => $this->configuration['title'],
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
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    $this->configuration['title'] = (string) $form_state->getValue('title');
    parent::submitConfigurationForm($form, $form_state);
  }

}
