<?php

namespace Drupal\eca\Entity;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\eca\PluginManager\Action;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\PluginManager\Event;
use Drupal\eca\PluginManager\Modeller;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\DependencyCalculation;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trait to provide all required services for ECA config entities.
 */
trait EcaTrait {

  /**
   * ECA modeller plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Modeller|null
   */
  protected ?Modeller $modellerPluginManager;

  /**
   * ECA event plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Event|null
   */
  protected ?Event $eventPluginManager;

  /**
   * ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionPluginManager;

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionPluginManager;

  /**
   * ECA action service.
   *
   * @var \Drupal\eca\Service\Actions|null
   */
  protected ?Actions $actionServices;

  /**
   * ECA condition service.
   *
   * @var \Drupal\eca\Service\Conditions|null
   */
  protected ?Conditions $conditionServices;

  /**
   * The dependency calculation service.
   *
   * @var \Drupal\eca\Service\DependencyCalculation|null
   */
  protected ?DependencyCalculation $dependencyCalculation;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel|null
   */
  protected ?LoggerChannel $logger;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $token;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|null
   */
  protected ?EntityFieldManagerInterface $entityFieldManager;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|null
   */
  protected ?MessengerInterface $messenger;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|null
   */
  protected ?FormBuilderInterface $formBuilder;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected ?Request $request;

  /**
   * Initializes the modeller plugin manager.
   *
   * @return \Drupal\eca\PluginManager\Modeller
   *   The modeller plugin manager.
   */
  protected function modellerPluginManager(): Modeller {
    if (!isset($this->modellerPluginManager)) {
      $this->modellerPluginManager = \Drupal::service('plugin.manager.eca.modeller');
    }
    return $this->modellerPluginManager;
  }

  /**
   * Initializes the event plugin manager.
   *
   * @return \Drupal\eca\PluginManager\Event
   *   The event plugin manager.
   */
  protected function eventPluginManager(): Event {
    if (!isset($this->eventPluginManager)) {
      $this->eventPluginManager = \Drupal::service('plugin.manager.eca.event');
    }
    return $this->eventPluginManager;
  }

  /**
   * Initializes the condition plugin manager.
   *
   * @return \Drupal\eca\PluginManager\Condition
   *   The condition plugin manager.
   */
  protected function conditionPluginManager(): Condition {
    if (!isset($this->conditionPluginManager)) {
      $this->conditionPluginManager = \Drupal::service('plugin.manager.eca.condition');
    }
    return $this->conditionPluginManager;
  }

  /**
   * Initializes the action plugin manager.
   *
   * @return \Drupal\Core\Action\ActionManager
   *   The action plugin manager.
   */
  protected function actionPluginManager(): ActionManager {
    if (!isset($this->actionPluginManager)) {
      $this->actionPluginManager = Action::get()->getDecoratedActionManager();
    }
    return $this->actionPluginManager;
  }

  /**
   * Initializes the action service.
   *
   * @return \Drupal\eca\Service\Actions
   *   The condition services.
   */
  protected function actionServices(): Actions {
    if (!isset($this->actionServices)) {
      $this->actionServices = \Drupal::service('eca.service.action');
    }
    return $this->actionServices;
  }

  /**
   * Initializes the condition service.
   *
   * @return \Drupal\eca\Service\Conditions
   *   The condition services.
   */
  protected function conditionServices(): Conditions {
    if (!isset($this->conditionServices)) {
      $this->conditionServices = \Drupal::service('eca.service.condition');
    }
    return $this->conditionServices;
  }

  /**
   * Initializes the dependency calculation service.
   *
   * @return \Drupal\eca\Service\DependencyCalculation
   *   The dependency calculation services.
   */
  protected function dependencyCalculation(): DependencyCalculation {
    if (!isset($this->dependencyCalculation)) {
      $this->dependencyCalculation = \Drupal::service('eca.service.dependency_calculation');
    }
    return $this->dependencyCalculation;
  }

  /**
   * Returns the ECA logger channel as a service.
   *
   * @return \Drupal\Core\Logger\LoggerChannel
   *   The logger channel service.
   */
  protected function logger(): LoggerChannel {
    if (!isset($this->logger)) {
      $this->logger = \Drupal::service('logger.channel.eca');
    }
    return $this->logger;
  }

  /**
   * Returns the ECA token service.
   *
   * @return \Drupal\eca\Token\TokenInterface
   *   The ECA token service.
   */
  protected function token(): TokenInterface {
    if (!isset($this->token)) {
      $this->token = \Drupal::service('eca.token_services');
    }
    return $this->token;
  }

  /**
   * Returns the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function entityFieldManager(): EntityFieldManagerInterface {
    if (!isset($this->entityFieldManager)) {
      $this->entityFieldManager = \Drupal::service('entity_field.manager');
    }
    return $this->entityFieldManager;
  }

  /**
   * Initializes the messenger service.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   The messenger service.
   */
  protected function messenger(): MessengerInterface {
    if (!isset($this->messenger)) {
      $this->messenger = \Drupal::messenger();
    }
    return $this->messenger;
  }

  /**
   * Initializes the form builder service.
   *
   * @return \Drupal\Core\Form\FormBuilderInterface
   *   The form builder service.
   */
  protected function formBuilder(): FormBuilderInterface {
    if (!isset($this->formBuilder)) {
      $this->formBuilder = \Drupal::formBuilder();
    }
    return $this->formBuilder;
  }

  /**
   * Initialize the request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The initialized request.
   */
  protected function request(): Request {
    if (!isset($this->request)) {
      $this->request = \Drupal::request();
    }
    return $this->request;
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup(): void {
    // Need to manually load the action plugin manager, since it is an instance
    // provided by the decorator of that service, not by the service container.
    // @see https://www.drupal.org/project/eca/issues/3507815
    if (property_exists($this, '_serviceIds') && isset($this->_serviceIds['actionPluginManager'])) {
      unset($this->_serviceIds['actionPluginManager']);
      $this->actionPluginManager();
    }
    $parent_class = get_parent_class($this);
    if ($parent_class && method_exists($parent_class, '__wakeup')) {
      // @phpstan-ignore-next-line
      parent::__wakeup();
    }
  }

}
