<?php

namespace Drupal\dashboard\Plugin\Block;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a dashboard site status block.
 *
 * @internal
 */
#[Block(
  id: "dashboard_site_status",
  admin_label: new TranslatableMarkup("Site Status"),
  category: new TranslatableMarkup("Dashboard"),
)]
final class SiteStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new SiteStatusBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\system\SystemManager $systemManager
   *   The system manager service.
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected SystemManager $systemManager, protected AccessManagerInterface $accessManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('system.manager'),
      $container->get('access_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $requirements = array_filter($this->systemManager->listRequirements(), function (array $requirement) {
      return isset($requirement['severity']) && $requirement['severity'] === $this->systemManager::REQUIREMENT_ERROR;
    });
    $requirements = array_map(function (array $requirement) {
      unset($requirement['description']);
      return $requirement;
    }, $requirements);
    $grouped_requirements = [];
    $grouped_requirements['errors']['title'] = $this->t('Errors found');
    $grouped_requirements['errors']['type'] = 'error';
    $grouped_requirements['errors']['items'] = $requirements;

    $build = [];
    $build['errors'] = [
      '#theme' => 'status_report_grouped',
      '#priorities' => [
        'error',
      ],
      '#grouped_requirements' => $grouped_requirements,
    ];
    $build['more'] = [
      '#type' => 'link',
      '#title' => $this->t('More information'),
      '#url' => Url::fromRoute('system.status', [], ['fragment' => 'error']),
      '#access' => $this->accessManager->checkNamedRoute('system.status'),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) : AccessResultInterface {
    $hasErrors = $this->systemManager->checkRequirements();
    return AccessResult::allowedIf($hasErrors)->andIf(AccessResult::allowedIf($this->accessManager->checkNamedRoute('system.status')));
  }

}
