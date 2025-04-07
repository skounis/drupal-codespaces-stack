<?php

namespace Drupal\scheduler_rules_integration\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rules\Core\RulesActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base class on which all Scheduler Rules actions are built.
 */
class SchedulerRulesActionBase extends RulesActionBase {

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Constructs a SchedulerRulesActionBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeId = $plugin_definition['entity_type_id'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Gives a warning when an entity is not enabled for Scheduler.
   *
   * This is called from actions that attempt to set or remove a Scheduler date
   * value when the entity type is not enabled for that process.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object being processed by the action.
   * @param string $process
   *   The process that is not enabled, either 'publish' or 'unpublish'.
   */
  public function notEnabledWarning(EntityInterface $entity, string $process) {
    $action = $this->summary();
    $activity = ($process == 'publish') ? $this->t('scheduled publishing') : $this->t('scheduled unpublishing');
    $condition = $this->t('@bundle_label is enabled for @activity', [
      '@bundle_label' => $entity->getEntityType()->getBundleLabel(),
      '@activity' => $activity,
    ]);

    $bundle_field = $entity->getEntityType()->get('entity_keys')['bundle'];
    $type_name = $entity->$bundle_field->entity->label();
    $type_id = $entity->$bundle_field->entity->bundle();
    $url = new Url("entity.$type_id.edit_form", [$type_id => $entity->bundle()]);
    $arguments = [
      '%action' => "'$action'",
      '@activity' => $activity,
      '%type' => $type_name,
      '@group' => $entity->getEntityType()->getPluralLabel(),
      '%condition' => "'$condition'",
      '@url' => $url->toString(),
    ];
    $link = Link::fromTextAndUrl($this->t('@type settings', ['@type' => $type_name]), $url)->toString();
    \Drupal::logger('scheduler')->warning('Action %action is not valid because @activity is not enabled for %type @group. Add the condition %condition to your Reaction Rule, or enable @activity via the %type settings.',
      $arguments + ['link' => $link]);

    \Drupal::messenger()->addMessage($this->t('Action %action is not valid because @activity is not enabled for %type @group. Add the condition %condition to your Reaction Rule, or enable @activity via the <a href="@url">%type</a> settings.',
      $arguments), 'warning', FALSE);
  }

}
