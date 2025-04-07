<?php

namespace Drupal\scheduler\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation class for scheduler entity plugins.
 *
 * @package Drupal\scheduler\Annotation
 *
 * @Annotation
 */
class SchedulerPlugin extends Plugin {

  /**
   * The internal id / machine name of the plugin.
   *
   * This is usually the entity_type_id followed by '_scheduler'.
   *
   * @var string
   */
  public $id;

  /**
   * The readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Description of plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The entity type.
   *
   * @var string
   */
  public $entityType;

  /**
   * Module name that plugin requires.
   *
   * @var string
   */
  public $dependency;

  /**
   * The name of the type/bundle field for the entity (optional).
   *
   * The default is the value of entity type 'bundle' key, so this property is
   * only needed if the entity type does not have a 'bundle' field.
   *
   * @var string
   */
  public $typeFieldName;

  /**
   * The Form ID of the devel generate form (optional).
   *
   * @var string
   */
  public $develGenerateForm = '';

  /**
   * The route of the collection overview page (optional).
   *
   * The default is entity.{$entityType}.collection so this property only needs
   * to be specified if that route is not the correct one.
   *
   * @var string
   */
  public $collectionRoute;

  /**
   * The route of the scheduled view on the user profile page (optional).
   *
   * @var string
   */
  public $userViewRoute = '';

  /**
   * The event class for Scheduler events relating to activity on the entity.
   *
   * This is optional, and if not specified, will default to the standard class
   *   \Drupal\scheduler\Event\Scheduler{EntityType}Events
   * The class must be in UpperCamelCase with no underscores, so if entityType
   * contains underscores then this property must be specified. The convention
   * in this case is to convert each word to upper case and remove underscores.
   *
   * @var string
   */
  public $schedulerEventClass;

  /**
   * The name of the publish action for the entity type (optional).
   *
   * This is used when the action name does not match the default pattern of
   * {entity type id}_publish_action.
   *
   * @var string
   */
  public $publishAction;

  /**
   * The name of the unpublish action for the entity type (optional).
   *
   * This is used when the action name does not match the default pattern of
   * {entity type id}_unpublish_action.
   *
   * @var string
   */
  public $unpublishAction;

}
