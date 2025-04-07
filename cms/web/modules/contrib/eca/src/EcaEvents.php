<?php

namespace Drupal\eca;

/**
 * Defines events provided by the ECA core module.
 */
final class EcaEvents {

  /**
   * Dispatched before initial successor execution of an ECA configuration.
   *
   * This event is fired right after a system event applied to an existent
   * and enabled ECA configuration, so that the configured ECA logic is about
   * to be executed.
   *
   * It should be noted that one ECA configuration may contain multiple events
   * to react upon. Also please note that ECA logic may trigger further events,
   * resulting in nested and repetitive executions of ECA configurations.
   *
   * @Event
   *
   * @var string
   */
  public const BEFORE_INITIAL_EXECUTION = 'eca.execution.initial.before';

  /**
   * Dispatched after initial successor execution of an ECA configuration.
   *
   * @Event
   *
   * @var string
   */
  public const AFTER_INITIAL_EXECUTION = 'eca.execution.initial.after';

  /**
   * Dispatched before a single action is being executed.
   *
   * Before the action is executed, an access check will be performed. Therefore
   * this event may be also used to do preparations for the access check.
   * Execution will not happen when the access check evaluates to be false.
   *
   * @var string
   */
  public const BEFORE_ACTION_EXECUTION = 'eca.execution.action.before';

  /**
   * Dispatched after a single action got executed.
   *
   * Please note that this event is not always necessarily to be followed by
   * BEFORE_ACTION_EXECUTION, as the access check may be evaluated to be false
   * and thus an actual execution of the action might not happen.
   *
   * Another thing that may happen are exceptions. If anything within the
   * access check or during action execution goes wrong, exceptions will
   * break out the regular execution logic. Whether or not an exception was
   * thrown, this event will be fired.
   */
  public const AFTER_ACTION_EXECUTION = 'eca.execution.action.after';

  /**
   * Dispatched when a token is about to be generated.
   *
   * @Event
   *
   * @var string
   */
  public const TOKEN = 'eca_base.token';

}
