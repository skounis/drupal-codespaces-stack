<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Switch current account to the service user.
 *
 * @Action(
 *   id = "eca_switch_service_account",
 *   label = @Translation("User: switch to service user"),
 *   description = @Translation("Switch to the globally configured service account."),
 *   eca_version_introduced = "2.1.3"
 * )
 */
class SwitchServiceAccount extends SwitchAccount {

  /**
   * The globally configured service user.
   *
   * @var string
   */
  protected string $serviceUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->serviceUser = trim((string) $container->get('config.factory')->get('eca.settings')->get('service_user'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $default = parent::defaultConfiguration();
    unset($default['user_id']);
    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['user_id']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    unset($this->configuration['user_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $user = NULL;
    $storage = $this->entityTypeManager->getStorage('user');
    if ($this->serviceUser === '') {
      return;
    }
    if (ctype_digit($this->serviceUser)) {
      $user = $storage->load($this->serviceUser);
    }
    elseif (Uuid::isValid($this->serviceUser)) {
      $users = $storage->loadByProperties(['uuid' => $this->serviceUser]);
      $user = reset($users);
    }
    $this->accountSwitcher->switchTo($this, $user);
  }

}
