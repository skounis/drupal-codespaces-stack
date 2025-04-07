<?php

namespace Drupal\eca_content\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Sets validation errors, if any, resulting from ECA processes.
 */
final class EcaConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The trigger event service.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * The entity types service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new self();
    $instance->triggerEvent = $container->get('eca.trigger_event');
    $instance->entityTypes = $container->get('eca.service.content_entity_types');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint) {
    $this->triggerEvent->dispatchFromPlugin('content_entity:validate', $entity, $this->entityTypes, $this);
  }

  /**
   * Returns the current execution context of the validation run.
   *
   * @return \Symfony\Component\Validator\Context\ExecutionContextInterface
   *   The context of the validation run.
   */
  public function getContext(): ExecutionContextInterface {
    return $this->context;
  }

}
