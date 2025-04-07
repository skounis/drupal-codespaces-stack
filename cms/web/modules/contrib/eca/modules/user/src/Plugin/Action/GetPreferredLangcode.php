<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Load the currently logged in user into the token environment.
 *
 * @Action(
 *   id = "eca_get_preferred_langcode",
 *   label = @Translation("User: get preferred language code"),
 *   description = @Translation("Get the preferred language code and store it as a token."),
 *   eca_version_introduced = "2.0.0",
 *   type = "user"
 * )
 */
class GetPreferredLangcode extends ConfigurableActionBase {

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
      '#description' => $this->t('The language code will be stored into this specified token.'),
      '#default_value' => $this->configuration['token_name'],
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = trim((string) $form_state->getValue('token_name', ''));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!is_null($object) && !($object instanceof AccountInterface)) {
      $result = AccessResult::forbidden("The provided object is not a user account.");
    }
    else {
      $result = AccessResult::allowed();
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $user = $entity ?? $this->currentUser;
    if (!($user instanceof AccountInterface)) {
      throw new \InvalidArgumentException("The provided object is not a user account.");
    }

    $this->tokenService->addTokenData($this->configuration['token_name'], $user->getPreferredLangcode());
  }

}
