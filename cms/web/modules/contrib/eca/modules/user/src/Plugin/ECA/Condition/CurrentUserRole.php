<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\user\Entity\Role;

/**
 * Plugin implementation of the ECA condition of the current user's role.
 *
 * @EcaCondition(
 *   id = "eca_current_user_role",
 *   label = @Translation("Role of current user"),
 *   description = @Translation("Checks, whether the current user has a given role."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class CurrentUserRole extends BaseUser {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $userRoles = $this->currentUser->getRoles();
    $role = $this->configuration['role'];
    if ($role === '_eca_token') {
      $role = $this->getTokenValue('role', '');
    }
    $result = in_array($role, $userRoles, TRUE);
    return $this->negationCheck($result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'role' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $roles = [];
    /** @var \Drupal\user\RoleInterface $role */
    foreach (Role::loadMultiple() as $role) {
      $roles[$role->id()] = $role->label();
    }
    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('User role'),
      '#description' => $this->t('The user role to check, like <em>editor</em> or <em>administrator</em>.'),
      '#default_value' => $this->configuration['role'],
      '#options' => $roles,
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['role'] = $form_state->getValue('role');
    parent::submitConfigurationForm($form, $form_state);
  }

}
