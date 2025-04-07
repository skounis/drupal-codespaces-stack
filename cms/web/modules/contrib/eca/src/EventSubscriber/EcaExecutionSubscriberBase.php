<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base class for ECA execution subscribers.
 */
abstract class EcaExecutionSubscriberBase implements EventSubscriberInterface {

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new EcaExecutionSubscriberBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\eca\Token\TokenInterface $token_service
   *   ECA token service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TokenInterface $token_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenService = $token_service;
  }

}
