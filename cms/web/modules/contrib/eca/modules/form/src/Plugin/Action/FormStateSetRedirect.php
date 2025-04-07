<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Set the form state for a redirect.
 *
 * @Action(
 *   id = "eca_form_state_set_redirect",
 *   label = @Translation("Form state: set redirect"),
 *   description = @Translation("Set the redirect destination on the form state."),
 *   eca_version_introduced = "1.1.0",
 *   type = "form"
 * )
 */
class FormStateSetRedirect extends FormActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }
    $destination = $this->configuration['destination'] ?? '';
    if ($destination !== '') {
      $destination = (string) $this->tokenService->replaceClear($destination);
    }
    if ($destination === '') {
      $form_state->disableRedirect(TRUE);
    }
    else {
      try {
        $url = Url::fromUserInput($destination);
      }
      catch (\Exception $e) {
        $url = Url::fromUri($destination);
      }
      if ($url->isExternal()) {
        throw new \InvalidArgumentException('Redirects to external URLs are not supported.');
      }
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'destination' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['destination'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination URL'),
      '#description' => $this->t('Leave empty to disable redirect on the form state. Please note: External URLs are not supported.'),
      '#default_value' => $this->configuration['destination'],
      '#weight' => -49,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $destination = $form_state->getValue('destination', '');
    if (($destination === '') || ((mb_strpos($destination, '[') !== FALSE) && (mb_strpos($destination, ']')))) {
      // Either empty, or a token is provided. For both cases, no URL validation
      // will be performed.
      return;
    }
    $url = NULL;
    try {
      $url = Url::fromUserInput($destination);
    }
    catch (\Exception $e1) {
      try {
        $url = Url::fromUri($destination);
      }
      catch (\Exception $e2) {
        $form_state->setErrorByName('destination', $this->t('The provided destination is not a valid URL.'));
      }
    }
    if ($url && $url->isExternal()) {
      $form_state->setErrorByName('destination', $this->t('Redirects to external URLs are not supported.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['destination'] = $form_state->getValue('destination');
    parent::submitConfigurationForm($form, $form_state);
  }

}
