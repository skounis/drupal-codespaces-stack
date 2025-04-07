<?php

namespace Drupal\ai_translate\Controller;

use Drupal\Core\Controller\ControllerResolver;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ai_translate\Form\AiTranslateForm;
use Drupal\content_translation\Controller\ContentTranslationController;

/**
 * Overridden class for entity translation controllers.
 */
class ContentTranslationControllerOverride extends ContentTranslationController {

  /**
   * Helper to get a controller resolver service.
   *
   * @return \Drupal\Core\Controller\ControllerResolver
   *   The Controller Resolver service.
   */
  public function getControllerResolver(): ControllerResolver {

    /** @var \Drupal\Core\Controller\ControllerResolver $service */
    // @codingStandardsIgnoreLine @phpstan-ignore-next-line
    $service = \Drupal::service('controller_resolver');

    return $service;
  }

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = NULL;

    $parent_controller_id = $route_match->getRouteObject()->getDefault('_parent_controller');

    // Let the original controller build the form it wants to.
    if ($parent_controller = $this->getControllerResolver()->getControllerFromDefinition($parent_controller_id, $route_match->getRouteObject()->getPath())) {
      $build = call_user_func([$parent_controller[0], $parent_controller[1]], $route_match, $entity_type_id);
    }

    if (!$build) {

      // If anything is unexpected, just use our parent controller to generate
      // the build.
      $build = parent::overview($route_match, $entity_type_id);
    }

    return $this->formBuilder()->getForm(AiTranslateForm::class, $build);
  }

}
