<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions_test_api;

use Drupal\automatic_updates_extensions\ExtensionUpdateStage;
use Drupal\package_manager\PathLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\package_manager_test_api\ApiController as PackageManagerApiController;

/**
 * Provides API endpoint to interact with stage directory in functional tests.
 */
class ApiController extends PackageManagerApiController {

  /**
   * {@inheritdoc}
   */
  protected $finishedRoute = 'automatic_updates_extensions_test_api.finish';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ExtensionUpdateStage::class),
      $container->get(PathLocator::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function createAndApplyStage(Request $request): string {
    $id = $this->stage->begin($request->get('projects', []));
    $this->stage->stage();
    $this->stage->apply();
    return $id;
  }

}
