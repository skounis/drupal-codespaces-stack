<?php

namespace Drupal\eca_language\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca_language\Event\LanguageNegotiateEvent;

/**
 * Get the currently used language code.
 *
 * @Action(
 *   id = "eca_get_current_langcode",
 *   label = @Translation("Language: get code"),
 *   description = @Translation("Get the currently used or negotiated language code."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class GetCurrentLangcode extends LanguageActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The language code will be stored into this specified token.'),
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['token_name'] = trim($form_state->getValue('token_name', ''));
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $token_name = (string) $this->configuration['token_name'];
    $langcode = isset($this->event) && ($this->event instanceof LanguageNegotiateEvent) ? $this->event->langcode : $this->languageManager->getCurrentLanguage()->getId();
    $this->tokenService->addTokenData($token_name, $langcode);
  }

}
