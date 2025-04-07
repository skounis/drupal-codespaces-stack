<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Trait provides configuration functions for user conditions.
 *
 * This only applies to conditions that are not dealing with the current user
 * but with a configurable user entity instead.
 */
trait UserTrait {

  /**
   * Loads the user specified in the "account" field of the configuration.
   *
   * The configuration value could either be a user ID or a token. This method
   * loads the user entity from the token environment and if it either receives
   * an AccountInterface or numeric ID, it loads the associated user entity
   * from there.
   *
   * @return \Drupal\user\UserInterface|null
   *   The configured user entity if found, NULL otherwise.
   */
  protected function loadUserAccount(): ?UserInterface {
    $account = $this->tokenService->getOrReplace($this->configuration['account']);
    if ($account instanceof AccountInterface) {
      if (!($account instanceof UserInterface)) {
        $account = $account->id();
      }
    }
    elseif (!is_numeric($account)) {
      $account = $this->tokenService->replaceClear($this->configuration['account']);

      // @see user_tokens().
      if ((string) $account === 'not yet assigned') {
        $account = 0;
      }
    }
    if (is_numeric($account)) {
      /**
       * @var \Drupal\user\UserInterface $account
       */
      try {
        $account = $this->entityTypeManager->getStorage('user')->load($account);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $account = NULL;
      }
    }
    return ($account instanceof UserInterface) ? $account : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'account' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User account'),
      '#description' => $this->t('The ID of an account or a token with a stored account entity.'),
      '#default_value' => $this->configuration['account'],
      '#weight' => -20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['account'] = $form_state->getValue('account');
    parent::submitConfigurationForm($form, $form_state);
  }

}
