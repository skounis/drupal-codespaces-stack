<?php

namespace Drupal\eca_user\Plugin\Action;

use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca_user\AccountSwitcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Switch back current account.
 *
 * @Action(
 *   id = "eca_switch_back",
 *   label = @Translation("User: switch back"),
 *   description = @Translation("Switch to previous user account."),
 *   eca_version_introduced = "2.1.4"
 * )
 */
class SwitchBack extends ActionBase {

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
  public function execute(): void {
    $this->accountSwitcher->switchBack();
  }

}
