<?php

namespace Drupal\scheduler;

/**
 * Interface for Scheduler entity plugin definition.
 */
interface SchedulerPluginInterface {

  /**
   * Get the label.
   *
   * @return mixed
   *   The label.
   */
  public function label();

  /**
   * Get the description.
   *
   * @return mixed
   *   The description.
   */
  public function description();

  /**
   * Get the type of entity supported by this plugin.
   *
   * @return string
   *   The name of the entity type.
   */
  public function entityType();

  /**
   * Get module dependency.
   *
   * @return string
   *   The name of the required module.
   */
  public function dependency();

  /**
   * Get the name of the "type" field for the entity.
   *
   * @return string
   *   The name of the type/bundle field for this entity type.
   */
  public function typeFieldName();

  /**
   * Get the id of the Devel Generate form for this entity type. Optional.
   *
   * @return string
   *   The form id.
   */
  public function develGenerateForm();

  /**
   * Get the route of the entity collection page.
   *
   * Optional. Defaults to entity.{entity type id}.collection.
   *
   * @return string
   *   The route id.
   */
  public function collectionRoute();

  /**
   * Get the route of the user page scheduled view. Optional.
   *
   * @return string
   *   The route id.
   */
  public function userViewRoute();

  /**
   * Get the scheduler event class.
   *
   * Optional. Defaults to '\Drupal\scheduler\Event\Scheduler{Type}Events' the
   * event class within the Scheduler module namespace.
   *
   * @return string
   *   The event class.
   */
  public function schedulerEventClass();

  /**
   * Get the publish action name of the entity type.
   *
   * Optional. Defaults to the commonly used {entity type id}_publish_action.
   *
   * @return string
   *   The action name.
   */
  public function publishAction();

  /**
   * Get the unpublish action name of the entity type.
   *
   * Optional. Defaults to the commonly used {entity type id}_unpublish_action.
   *
   * @return string
   *   The action name.
   */
  public function unpublishAction();

  /**
   * Get all the type/bundle objects for this entity.
   *
   * @return array
   *   The type/bundle objects.
   */
  public function getTypes();

  /**
   * Get the form IDs for entity add/edit forms.
   *
   * @return array
   *   A list of add/edit form ids for all bundles in this entity type.
   */
  public function entityFormIds();

  /**
   * Get the form IDs for entity type add/edit forms.
   *
   * @return array
   *   A list of add/edit form ids for this entity type.
   */
  public function entityTypeFormIds();

  /**
   * Return all supported entity edit form display modes.
   *
   * \Drupal\Core\Entity\EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE
   * is the 'default' display mode and this is always supported. If there are no
   * other supported modes then this function does not need to be implemented in
   * the plugin. However if additional form display modes are provided by other
   * modules and Scheduler has been updated to support these modes for editing
   * the entity, then the plugin implementation of this function should return
   * all supported modes including 'default'. The implementation does not need
   * to check if the third-party module is actually available or enabled.
   *
   * @return array
   *   A list of entity form display mode ids.
   */
  public function entityFormDisplayModes();

}
