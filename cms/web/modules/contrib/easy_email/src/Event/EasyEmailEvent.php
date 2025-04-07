<?php

namespace Drupal\easy_email\Event;

use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the Entity Email event.
 *
 * @see \Drupal\easy_email\Event\EasyEmailEvents
 */
class EasyEmailEvent extends Event {

  /**
   * @var \Drupal\easy_email\Entity\EasyEmailInterface
   */
  protected $email;

  /**
   * Constructs a new EasyEmailEvent.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   *   The entity email
   */
  public function __construct(EasyEmailInterface $email) {
    $this->email = $email;
  }

  /**
   * Gets the entity email.
   *
   * @return \Drupal\easy_email\Entity\EasyEmailInterface
   *   The entity email
   */
  public function getEmail(): EasyEmailInterface {
    return $this->email;
  }

}
