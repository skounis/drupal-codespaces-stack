<?php

namespace Drupal\eca;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Entity\Objects\EcaObject;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\Plugin\CleanupInterface;
use Drupal\eca\Plugin\ObjectWithPluginInterface;
use Drupal\eca\PluginManager\Event as PluginManagerEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Executes enabled ECA config regards applying events.
 */
class Processor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The manager for ECA event plugins.
   *
   * @var \Drupal\eca\PluginManager\Event
   */
  protected PluginManagerEvent $eventPluginManager;

  /**
   * A shortened list of ECA events where execution was applied.
   *
   * This list is only used as a temporary reminder for being able to recognize
   * a possible infinite recursion.
   *
   * @var array
   */
  protected array $executionHistory = [];

  /**
   * A parameterized threshold of the maximum allowed level of recursion.
   *
   * @var int
   */
  protected int $recursionThreshold;

  /**
   * A flag indicating whether an error was already logged regards recursion.
   *
   * The flag is used to prevent log flooding, as this may quickly happen when
   * infinite recursion would happen a lot. The site owner should see at least
   * one of such an error and may (hopefully) react accordingly.
   *
   * @var bool
   */
  protected bool $recursionErrorLogged = FALSE;

  /**
   * The Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\Processor
   *   The service instance.
   */
  public static function get(): Processor {
    return \Drupal::service('eca.processor');
  }

  /**
   * Processor constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\eca\PluginManager\Event $event_plugin_manager
   *   The manager for ECA event plugins.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal state.
   * @param int $recursion_threshold
   *   A parameterized threshold of the maximum allowed level of recursion.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, EventDispatcherInterface $event_dispatcher, PluginManagerEvent $event_plugin_manager, StateInterface $state, int $recursion_threshold) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->eventPluginManager = $event_plugin_manager;
    $this->recursionThreshold = $recursion_threshold;
    $this->state = $state;
  }

  /**
   * Determines, if the current stack trace is within ECA processing an event.
   *
   * @return bool
   *   TRUE, if the current stack trace is within ECA processing an event, FALSE
   *   otherwise.
   */
  public function isEcaContext(): bool {
    return (bool) $this->executionHistory || $this->state->get('_eca_internal_test_context');
  }

  /**
   * Main method that executes ECA config regards applying events.
   *
   * @param object $event
   *   The event being triggered.
   * @param string $event_name
   *   The event name that was triggered.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \InvalidArgumentException
   *   When the given event is not of a documented object type.
   */
  public function execute(object $event, string $event_name): void {
    if (!($event instanceof Event)) {
      throw new \InvalidArgumentException(sprintf('Passed $event parameter is not of an expected event object type, %s given', get_class($event)));
    }

    $subscribed = current($this->state->get('eca.subscribed', [])[$event_name] ?? []);
    if (!$subscribed) {
      $this->logger->error('The ECA processor got invoked for executing, but no subscribed configuration was found.');
      return;
    }

    /** @var \Drupal\eca\Entity\EcaStorage $eca_storage */
    $eca_storage = $this->entityTypeManager->getStorage('eca');
    $context = ['%event' => $event_name];

    foreach ($subscribed as $eca_id => $wildcards) {
      $context['%ecaid'] = $eca_id;
      unset($context['%eventid'], $context['%eventlabel']);
      foreach ($wildcards as $eca_event_id => $wildcard) {
        $context['%eventid'] = $eca_event_id;
        unset($context['%ecalabel'], $context['%eventlabel']);

        /** @var \Symfony\Contracts\EventDispatcher\Event $event */
        if (!$this->wildcardApplies($event, $event_name, $wildcard, $context)) {
          $this->logger->debug('Appliance check for event %event defined by ECA ID %ecaid resulted to not apply, successors will not be executed.', $context);
          continue;
        }

        $this->logger->debug('Begin applying process for event %event defined by ECA ID %ecaid.', $context);

        /** @var \Drupal\eca\Entity\Eca|null $eca */
        $eca = $eca_storage->load($eca_id);
        if (!$eca) {
          // If an ECA model got deleted, we may end up here and then ignore
          // this model as it not longer exists.
          continue;
        }
        $context['%ecalabel'] = $eca->label();

        if (!($ecaEvent = $eca->getEcaEvent($eca_event_id))) {
          $this->logger->error('Event object %eventid does not exist in configuration of ECA ID %ecaid.', $context);
          continue;
        }
        $context['%eventlabel'] = $ecaEvent->getLabel();

        /** @var \Symfony\Contracts\EventDispatcher\Event $event */
        if (!$ecaEvent->execute(NULL, $event, $context)) {
          $this->logger->debug('Event object execution returned false, successors will not be executed.', $context);
          continue;
        }

        // We need to check whether this is the root of all execution calls,
        // for being able to purge the whole execution history once it is not
        // needed anymore.
        $is_root_execution = empty($this->executionHistory);
        // Take a look for a repetitive execution order. If we find one,
        // we see it as the beginning of infinite recursion and stop.
        if (!$is_root_execution && $this->recursionThresholdSurpassed($eca, $ecaEvent)) {
          if (!$this->recursionErrorLogged) {
            $this->logger->error('Recursion within configured ECA events detected. Please adjust your ECA configuration so that it avoids infinite loops. Affected event: %eventlabel (%eventid) from ECA %ecalabel (%ecaid).', $context);
            $this->recursionErrorLogged = TRUE;
          }
          continue;
        }

        // Temporarily keep in mind on which ECA event object execution is
        // about to be applied. If that behavior starts to repeat, then halt
        // the execution pipeline to prevent infinite recursion.
        $this->executionHistory[] = $eca->id() . ':' . $ecaEvent->getId();

        $before_event = new BeforeInitialExecutionEvent($eca, $ecaEvent, $event, $event_name);
        $this->eventDispatcher->dispatch($before_event, EcaEvents::BEFORE_INITIAL_EXECUTION);

        // Now that we have any required context, we may execute the logic.
        $this->logger->info('Start %eventlabel (%eventid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        try {
          $this->executeSuccessors($eca, $ecaEvent, $event, $context);
        }
        catch (\Exception $ex) {
          throw $ex;
        }
        finally {
          // At this point, no nested triggering of events happened or was
          // prevented by something else. Therefore remove the last added
          // item from the history stack as it's not needed anymore.
          array_pop($this->executionHistory);

          $pre_state = $before_event->getPrestate(NULL);
          $this->eventDispatcher->dispatch(new AfterInitialExecutionEvent($eca, $ecaEvent, $event, $event_name, $pre_state), EcaEvents::AFTER_INITIAL_EXECUTION);

          if ($is_root_execution) {
            // Forget what we've done here. We only take care for nested
            // triggering of events regarding possible infinite recursion.
            // By resetting the array, all root-level executions will not know
            // anything from each other.
            $this->executionHistory = [];
          }

          $this->logger->debug('Finished applying process for event %event defined by ECA ID %ecaid.', $context);
        }
      }
    }
  }

  /**
   * Whether the given event passes the appliance of the given wildcard.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The system event.
   * @param string $event_name
   *   The event name that was triggered.
   * @param string $wildcard
   *   The wildcard for checking appliance.
   * @param array $context
   *   List of key value pairs, used to generate meaningful log messages.
   *
   * @return bool
   *   Returns TRUE if the event passes, FALSE otherwise.
   */
  protected function wildcardApplies(Event $event, string $event_name, string $wildcard, array $context): bool {
    $event_plugin_id = $this->eventPluginManager->getPluginIdForSystemEvent($event_name);
    if (NULL === $event_plugin_id) {
      $this->logger->critical("Missing event plugin for system event %event", $context);
      return FALSE;
    }
    $event_plugin_class = $this->eventPluginManager->getDefinition($event_plugin_id)['class'];
    return call_user_func($event_plugin_class . '::appliesForWildcard', $event, $event_name, $wildcard);
  }

  /**
   * Executes the successors.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param \Drupal\eca\Entity\Objects\EcaObject $eca_object
   *   The ECA item that was just executed and looks for its successors.
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The event that was originally triggered.
   * @param array $context
   *   List of key value pairs, used to generate meaningful log messages.
   */
  protected function executeSuccessors(Eca $eca, EcaObject $eca_object, Event $event, array $context): void {
    $executedSuccessorIds = [];
    try {
      foreach ($eca->getSuccessors($eca_object, $event, $context) as $successor) {
        $context['%actionlabel'] = $successor->getLabel();
        $context['%actionid'] = $successor->getId();
        if (in_array($successor->getId(), $executedSuccessorIds, TRUE)) {
          $this->logger->debug('Prevent duplicate execution of %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event.', $context);
          continue;
        }
        $this->logger->info('Execute %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        if ($successor->execute($eca_object, $event, $context)) {
          $executedSuccessorIds[] = $successor->getId();
          $this->executeSuccessors($eca, $successor, $event, $context);
        }
      }
    }
    catch (\Exception $ex) {
      throw $ex;
    }
    finally {
      if ($eca_object instanceof ObjectWithPluginInterface) {
        $plugin = $eca_object->getPlugin();
        if ($plugin instanceof CleanupInterface) {
          $plugin->cleanupAfterSuccessors();
        }
      }
    }
  }

  /**
   * Checks the ECA event object whether it surpasses the recursion threshold.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $ecaEvent
   *   The ECA event object to check for.
   *
   * @return bool
   *   Returns TRUE when recursion threshold was surpassed, FALSE otherwise.
   */
  protected function recursionThresholdSurpassed(Eca $eca, EcaEvent $ecaEvent): bool {
    $current = $eca->id() . ':' . $ecaEvent->getId();
    if (!in_array($current, $this->executionHistory, TRUE)) {
      return FALSE;
    }
    $block_size = -1;
    $recursion_level = 1;
    $executed_block = [];
    $entry = end($this->executionHistory);
    while ($entry !== FALSE) {
      array_unshift($executed_block, $entry);
      if ($entry === $current) {
        $block_size = count($executed_block);
        break;
      }
      $entry = prev($this->executionHistory);
    }
    while (!($recursion_level > $this->recursionThreshold)) {
      $entry = end($executed_block);
      $block_index = 0;
      while ($entry !== FALSE) {
        if ($entry !== prev($this->executionHistory)) {
          break 2;
        }
        $block_index++;
        if ($block_index >= $block_size) {
          $recursion_level++;
          break;
        }
        $entry = prev($executed_block);
      }
    }
    return $recursion_level > $this->recursionThreshold;
  }

}
