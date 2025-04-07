<?php

namespace Drupal\eca\Plugin\ECA\Condition;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Interface for ECA provided conditions.
 */
interface ConditionInterface extends PluginFormInterface, ConfigurableInterface, DependentPluginInterface, PluginInspectionInterface, CacheableDependencyInterface, ContextAwarePluginInterface {

  /**
   * Resets stateful variables to their initial values.
   *
   * @return \Drupal\eca\Plugin\ECA\Condition\ConditionInterface
   *   This.
   */
  public function reset(): ConditionInterface;

  /**
   * Determines whether condition result will be negated.
   *
   * @return bool
   *   Whether the condition result will be negated.
   */
  public function isNegated(): bool;

  /**
   * Evaluates the condition and returns TRUE or FALSE accordingly.
   *
   * @return bool
   *   TRUE if the condition has been met, FALSE otherwise.
   */
  public function evaluate(): bool;

  /**
   * Sets the event that triggered the process in which this condition occurs.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The triggering event.
   *
   * @return $this
   */
  public function setEvent(Event $event): ConditionInterface;

  /**
   * Gets the event that triggered the process in which this condition occurs.
   *
   * @return \Symfony\Contracts\EventDispatcher\Event
   *   The triggering event.
   */
  public function getEvent(): Event;

}
