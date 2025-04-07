<?php

namespace Drupal\eca\Plugin\Action;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Interface for ECA provided actions.
 */
interface ActionInterface {

  /**
   * Whether this action is available outside of the scope of ECA.
   *
   * Most ECA actions are only viable within the scope of ECA. Some actions
   * however may also be useful elsewhere, for example in Views Bulk Operations.
   * For such an action, override this constant in your action class and set
   * it to TRUE. Default is FALSE, which means that this action will only be
   * made available in ECA.
   *
   * @return bool
   *   TRUE, if externally available, FALSE otherwise.
   */
  public static function externallyAvailable(): bool;

  /**
   * Sets the ECA action IDs.
   *
   * @param string $ecaModelId
   *   The ID of the containing ECA model.
   * @param string $actionId
   *   The ID of the action within the ECA model.
   *
   * @return $this
   */
  public function setEcaActionIds(string $ecaModelId, string $actionId): ActionInterface;

  /**
   * Sets the triggered event that leads to this action.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The triggered event.
   *
   * @return $this
   */
  public function setEvent(Event $event): ActionInterface;

  /**
   * Get the triggered event that leads to this action.
   *
   * @return \Symfony\Contracts\EventDispatcher\Event
   *   The triggered event.
   */
  public function getEvent(): Event;

  /**
   * Gets default configuration for this plugin.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  public function defaultConfiguration(): array;

  /**
   * Returns whether this action plugin handles exceptions.
   *
   * If so, ECA should not catch them. Defaults to false.
   *
   * @return bool
   *   Whether or not this action plugin handles exceptions.
   */
  public function handleExceptions(): bool;

  /**
   * Returns whether exceptions should be logged.
   *
   * @return bool
   *   Whether or not exceptions thrown by this action should be logged.
   */
  public function logExceptions(): bool;

}
