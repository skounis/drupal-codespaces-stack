<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Create a new user without saving it.
 *
 * @Action(
 *   id = "eca_new_user",
 *   label = @Translation("User: create new"),
 *   description = @Translation("Create a new user without saving it."),
 *   eca_version_introduced = "2.0.0",
 *   type = "user"
 * )
 */
class NewUser extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'name' => '',
      'mail' => '',
      'status' => FALSE,
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
      '#description' => $this->t('Provide the name of a token that holds the new user.'),
      '#weight' => -60,
      '#eca_token_reference' => TRUE,
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User name'),
      '#default_value' => $this->configuration['name'],
      '#description' => $this->t('The user name of the new user. If that name already exists, a hyphen followed by a number will be appended.'),
      '#required' => TRUE,
      '#weight' => -30,
    ];
    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => $this->configuration['mail'],
      '#description' => $this->t('The email address of the new user. If that email already exists for another user, this action will fail.'),
      '#required' => TRUE,
      '#weight' => -40,
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Status'),
      '#default_value' => $this->configuration['status'],
      '#description' => $this->t('Whether the user should be active or not.'),
      '#weight' => -20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['mail'] = $form_state->getValue('mail');
    $this->configuration['status'] = !empty($form_state->getValue('status'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler */
    $access_handler = $this->entityTypeManager->getHandler('user', 'access');
    return $access_handler->createAccess(NULL, $account, [], $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $mail = $this->tokenService->replaceClear($this->configuration['mail']);
    if (empty($mail)) {
      throw new \InvalidArgumentException('The email address is empty.');
    }
    /** @var \Drupal\user\UserInterface|bool $existingUser */
    $existingUser = user_load_by_mail($mail);
    if ($existingUser instanceof UserInterface) {
      throw new \InvalidArgumentException('A user account with email ' . $mail . ' already exists with ID ' . $existingUser->id() . '.');
    }

    $name = $this->tokenService->replaceClear($this->configuration['name']);
    if (empty($name)) {
      throw new \InvalidArgumentException('The name is empty.');
    }
    $testName = $name;
    $i = 0;
    while (user_load_by_name($testName)) {
      $i++;
      $testName = $name . '-' . $i;
    }
    $name = $testName;

    $user = User::create([
      'name' => $name,
      'mail' => $mail,
      'status' => (int) $this->configuration['status'],
    ]);
    $this->tokenService->addTokenData($this->configuration['token_name'], $user);
  }

}
