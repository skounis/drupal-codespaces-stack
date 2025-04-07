<?php

declare(strict_types=1);

namespace Drupal\klaro__testing\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Klaro Consent Manager Testing routes.
 */
final class KlaroTestingController extends ControllerBase {

  /**
   * Prepare stage for rendering through normal pipeline.
   */
  public function __invoke($stage): array {
    $build['content'] = [
      '#theme' => 'STAGE__' . $stage,
    ];
    return $build;
  }

}
