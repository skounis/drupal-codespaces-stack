<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\CleanupInterface;
use Drupal\eca_user\AccountSwitcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Switch current account.
 *
 * @Action(
 *   id = "eca_switch_account",
 *   label = @Translation("User: switch current account"),
 *   description = @Translation("Switch to given user account."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class SwitchAccount extends ConfigurableActionBase implements CleanupInterface {

  /**
   * The ECA account switcher service.
   *
   * @var \Drupal\eca_user\AccountSwitcher
   */
  protected AccountSwitcher $accountSwitcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->accountSwitcher = $container->get('eca_user.account_switcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'user_id' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['user_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User ID (UID)'),
      '#default_value' => $this->configuration['user_id'] ?? '',
      '#description' => $this->t('The numeric ID of the user account to switch to.'),
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['user_id'] = $form_state->getValue('user_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!isset($this->configuration['user_id']) || $this->configuration['user_id'] === '') {
      return;
    }
    $uid = (string) $this->tokenService->replaceClear($this->configuration['user_id']);
    if ($uid !== '' && ctype_digit($uid)) {
      $uid = (int) $uid;
      /**
       * @var \Drupal\user\UserInterface|null $user
       */
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      $this->accountSwitcher->switchTo($this, $user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupAfterSuccessors(): void {
    $this->accountSwitcher->cleanup($this);
  }

}
