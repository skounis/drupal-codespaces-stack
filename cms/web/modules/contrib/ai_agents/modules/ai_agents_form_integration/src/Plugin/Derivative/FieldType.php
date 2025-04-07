<?php

namespace Drupal\ai_agents_form_integration\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks for field type.
 */
class FieldType extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The route provider to load routes by name.
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a FieldUiLocalAction object.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteProviderInterface $route_provider, EntityTypeManagerInterface $entity_type_manager) {
    $this->routeProvider = $route_provider;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Implement similar to example.links.task.yml.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->get('field_ui_base_route')) {
        $this->derivatives['ai_agents_form_integration.field_type_' . $entity_type_id] = $base_plugin_definition;
        $this->derivatives['ai_agents_form_integration.field_type_' . $entity_type_id]['title'] = $this->t("Create with AI");
        $this->derivatives['ai_agents_form_integration.field_type_' . $entity_type_id]['route_name'] = 'ai_agents_form_integration.field_type_creation_' . $entity_type_id;
        $this->derivatives['ai_agents_form_integration.field_type_' . $entity_type_id]['appears_on'] = ['entity.' . $entity_type_id . '.field_ui_fields'];
        $this->derivatives['ai_agents_form_integration.field_type_' . $entity_type_id]['weight'] = 100;
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
