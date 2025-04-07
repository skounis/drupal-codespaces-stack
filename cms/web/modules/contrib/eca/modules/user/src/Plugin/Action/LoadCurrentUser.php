<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Load the currently logged in user into the token environment.
 *
 * @Action(
 *   id = "eca_token_load_user_current",
 *   label = @Translation("Current user: load"),
 *   description = @Translation("Load the current user and store it as a token."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class LoadCurrentUser extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['token_name' => 'user'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The loaded current user will be stored into this token.'),
      '#default_value' => $this->configuration['token_name'],
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if ($user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id())) {
      $token_name = trim($this->configuration['token_name'] ?? '');
      if ($token_name === '') {
        $token_name = 'user';
      }
      $this->tokenService->addTokenData($token_name, $user);
    }
  }

}
