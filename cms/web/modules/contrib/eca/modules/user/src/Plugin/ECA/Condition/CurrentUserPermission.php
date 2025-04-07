<?php

namespace Drupal\eca_user\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\user\PermissionHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ECA condition of the current user's permissions.
 *
 * @EcaCondition(
 *   id = "eca_current_user_permission",
 *   label = @Translation("Current user has permission"),
 *   description = @Translation("Checks, whether the current user has a given permission."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class CurrentUserPermission extends BaseUser {

  use PluginFormTrait;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandler
   */
  protected PermissionHandler $permissionHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->permissionHandler = $container->get('user.permissions');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $permission = $this->configuration['permission'];
    if ($permission === '_eca_token') {
      $permission = $this->getTokenValue('permission', '');
    }
    return $this->negationCheck($this->currentUser->hasPermission($permission));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'permission' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $permissions = [];
    foreach ($this->permissionHandler->getPermissions() as $permission => $def) {
      $permissions[$permission] = strip_tags((string) $def['title']);
    }
    asort($permissions);
    $form['permission'] = [
      '#type' => 'select',
      '#title' => $this->t('Permission'),
      '#description' => $this->t('The permission to check, like <em>administer node display</em>.'),
      '#default_value' => $this->configuration['permission'],
      '#options' => $permissions,
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['permission'] = $form_state->getValue('permission');
    parent::submitConfigurationForm($form, $form_state);
  }

}
