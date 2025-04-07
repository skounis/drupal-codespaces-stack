<?php

namespace Drupal\eca\Entity\Objects;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Action\ActionInterface as CoreActionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\AfterActionExecutionEvent;
use Drupal\eca\Event\BeforeActionExecutionEvent;
use Drupal\eca\Plugin\Action\ActionInterface;
use Drupal\eca\Plugin\ObjectWithPluginInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides an ECA item of type action for internal processing.
 */
class EcaAction extends EcaObject implements ObjectWithPluginInterface {

  /**
   * Action plugin.
   *
   * @var \Drupal\Core\Action\ActionInterface
   */
  protected CoreActionInterface $plugin;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
   */
  protected ?EventDispatcherInterface $eventDispatcher;

  /**
   * Event constructor.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param string $id
   *   The action ID provided by the modeller.
   * @param string $label
   *   The action label.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $event
   *   The ECA event object which started the process towards this action.
   * @param \Drupal\Core\Action\ActionInterface $plugin
   *   The action plugin.
   */
  public function __construct(Eca $eca, string $id, string $label, EcaEvent $event, CoreActionInterface $plugin) {
    parent::__construct($eca, $id, $label, $event);
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?EcaObject $predecessor, Event $event, array $context): bool {
    if (!parent::execute($predecessor, $event, $context)) {
      return FALSE;
    }

    $access_granted = FALSE;
    $exception_thrown = FALSE;
    if ($this->plugin instanceof ActionInterface) {
      $this->plugin->setEcaActionIds($this->getEca()->id(), $this->getId());
      $this->plugin->setEvent($event);
    }
    elseif (($this->plugin instanceof ConfigurableInterface) && !empty($this->plugin->getConfiguration()['replace_tokens'])) {
      // When this action plugin is not related with ECA directly, that external
      // action plugin might provide configuration input where it makes sense
      // to apply Token replacement. This will only be applied when this action
      // is explicitly configured with Token replacement enabled.
      $token = $this->token();
      $fields = $this->plugin->getConfiguration();
      array_walk_recursive($fields, static function (&$value) use ($token) {
        if (is_string($value) && !empty($value)) {
          $value = $token->replaceClear($value);
        }
      });
      $this->plugin->setConfiguration($fields);
    }
    $objects = $this->getObjects($this->plugin);
    foreach ($objects as $object) {
      if ($object instanceof TypedDataInterface) {
        $value = $object->getValue();
        if ($value instanceof EntityInterface) {
          $object = $value;
        }
      }

      $before_event = new BeforeActionExecutionEvent($this, $object, $event, $predecessor);
      $this->eventDispatcher()->dispatch($before_event, EcaEvents::BEFORE_ACTION_EXECUTION);

      try {
        /**
         * @var \Drupal\Core\Access\AccessResultReasonInterface|bool $access_result
         */
        $access_result = $this->plugin->access($object, NULL, TRUE);
        $access_granted = $access_result->isAllowed();
        if ($access_granted) {
          // @phpstan-ignore-next-line
          $this->plugin->execute($object);
        }
        else {
          $context['%reason'] = $access_result instanceof AccessResultReasonInterface ?
            $access_result->getReason() :
            'unknown';
          $this->logger()->warning('Access denied to %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event: %reason', $context);
        }
      }
      catch (\Exception $ex) {
        // @todo Remove in https://www.drupal.org/project/drupal/issues/2367555.
        if ($ex instanceof EnforcedResponseException) {
          throw $ex;
        }
        if ($ex instanceof FormAjaxException) {
          throw $ex;
        }
        $context['%exception_msg'] = $ex->getMessage();
        $context['%exception_trace'] = $ex->getTraceAsString();
        if (!($this->plugin instanceof ActionInterface) || $this->plugin->logExceptions()) {
          $this->logger()->error('Failed execution of %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event: %exception_msg.\n\n%exception_trace', $context);
        }
        if ($this->plugin instanceof ActionInterface && $this->plugin->handleExceptions()) {
          throw $ex;
        }
        if ($predecessor !== NULL && $predecessor->event->getPlugin()->handleExceptions()) {
          throw $ex;
        }
        $exception_thrown = TRUE;
      }
      finally {
        $pre_state = $before_event->getPrestate(NULL);
        $this->eventDispatcher()->dispatch(new AfterActionExecutionEvent($this, $object, $event, $predecessor, $pre_state, $access_granted, $exception_thrown), EcaEvents::AFTER_ACTION_EXECUTION);
      }
    }

    return $access_granted && !$exception_thrown;
  }

  /**
   * Get the plugin instance.
   *
   * @return \Drupal\Core\Action\ActionInterface
   *   The plugin instance.
   */
  public function getPlugin(): CoreActionInterface {
    return $this->plugin;
  }

  /**
   * Get the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher(): EventDispatcherInterface {
    if (!isset($this->eventDispatcher)) {
      // @phpstan-ignore-next-line
      $this->eventDispatcher = \Drupal::service('event_dispatcher');
    }
    return $this->eventDispatcher;
  }

}
