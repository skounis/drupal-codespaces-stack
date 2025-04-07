<?php

namespace Drupal\eca\Event;

use Drupal\eca\Entity\Objects\EcaAction;
use Drupal\eca\Entity\Objects\EcaObject;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatches after a single action got executed.
 */
class AfterActionExecutionEvent extends Event {

  /**
   * The ECA configuration.
   *
   * @var \Drupal\eca\Entity\Objects\EcaAction
   */
  protected EcaAction $ecaAction;

  /**
   * The object that the action operates on.
   *
   * @var mixed
   */
  protected mixed $object;

  /**
   * The triggering system event.
   *
   * @var \Symfony\Contracts\EventDispatcher\Event
   */
  protected Event $event;

  /**
   * The predecessor.
   *
   * @var \Drupal\eca\Entity\Objects\EcaObject
   */
  protected EcaObject $predecessor;

  /**
   * Array holding arbitrary variables the represent a pre-execution state.
   *
   * Can be used to hold and restore values after execution.
   *
   * @var array
   */
  protected array $prestate = [];

  /**
   * Whether access was granted or not.
   *
   * @var bool
   */
  protected bool $accessGranted;

  /**
   * Whether an exception was thrown or not.
   *
   * @var bool
   */
  protected bool $exceptionThrown;

  /**
   * The AfterActionExecutionEvent constructor.
   *
   * @param \Drupal\eca\Entity\Objects\EcaAction $ecaAction
   *   The action object as part of an ECA configuration.
   * @param mixed &$object
   *   The object that the action operates on.
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The triggering system event.
   * @param \Drupal\eca\Entity\Objects\EcaObject $predecessor
   *   The predecessor.
   * @param array &$prestate
   *   Array holding arbitrary variables of a prestate (if any).
   * @param bool $access_granted
   *   Whether access was granted or not.
   * @param bool $exception_thrown
   *   Whether an exception was thrown or not.
   */
  public function __construct(EcaAction $ecaAction, mixed &$object, Event $event, EcaObject $predecessor, array &$prestate, bool $access_granted, bool $exception_thrown) {
    $this->ecaAction = $ecaAction;
    $this->object = &$object;
    $this->event = $event;
    $this->predecessor = $predecessor;
    $this->prestate = &$prestate;
    $this->accessGranted = $access_granted;
    $this->exceptionThrown = $exception_thrown;
  }

  /**
   * Get the ECA action object.
   *
   * @return \Drupal\eca\Entity\Objects\EcaAction
   *   The ECA action object.
   */
  public function getEcaAction(): EcaAction {
    return $this->ecaAction;
  }

  /**
   * Get the object that the action operates on.
   *
   * @return mixed
   *   The object.
   */
  public function &getObject(): mixed {
    return $this->object;
  }

  /**
   * Get the applying system event.
   *
   * @return \Symfony\Contracts\EventDispatcher\Event
   *   The applying system event.
   */
  public function getEvent(): Event {
    return $this->event;
  }

  /**
   * Get the predecessor.
   *
   * @return \Drupal\eca\Entity\Objects\EcaObject
   *   The predecessor.
   */
  public function getPredecessor(): EcaObject {
    return $this->predecessor;
  }

  /**
   * Get the value of a prestate variable.
   *
   * @param string|null $name
   *   The name of the variable. Set to NULL to return the whole array.
   *
   * @return mixed
   *   The value. Returns NULL if not present.
   */
  public function &getPrestate(?string $name): mixed {
    if (!isset($name)) {
      return $this->prestate;
    }

    $value = NULL;
    if (isset($this->prestate[$name])) {
      $value = &$this->prestate[$name];
    }
    return $value;
  }

  /**
   * Whether access was granted or not.
   *
   * @return bool
   *   Returns TRUE if it was granted, FALSE otherwise.
   */
  public function accessGranted(): bool {
    return $this->accessGranted;
  }

  /**
   * Whether an exception was thrown or not.
   *
   * @return bool
   *   Returns TRUE if there was an exception, FALSE otherwise.
   */
  public function exceptionThrown(): bool {
    return $this->exceptionThrown;
  }

}
