<?php

namespace Drupal\ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the provider setup list.
 */
class ProviderSetupList extends ControllerBase {

  /**
   * System Manager Service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * ProviderSetupList constructor.
   *
   * @param \Drupal\system\SystemManager $system_manager
   *   The system manager service.
   */
  final public function __construct(SystemManager $system_manager) {
    $this->systemManager = $system_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('system.manager')
    );
  }

  /**
   * Display the provider setup list.
   *
   * @return array
   *   Render array.
   */
  public function list() {
    return $this->systemManager->getBlockContents();
  }

}
