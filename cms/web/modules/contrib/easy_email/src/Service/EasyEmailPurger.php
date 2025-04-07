<?php

namespace Drupal\easy_email\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class EasyEmailPurger implements EasyEmailPurgerInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  protected TimeInterface $time;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TimeInterface $time) {
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
  }

  public function purgeEmails(array $types = [], ?int $beforeTimestamp = NULL, ?int $limit = NULL) {
    $purge_conditions = $this->getPurgeConditions();
    foreach ($purge_conditions as $type => $interval_days) {
      if (!empty($types) && !in_array($type, $types, TRUE)) {
        unset($purge_conditions[$type]);
        continue;
      }
      if ($beforeTimestamp !== NULL) {
        $purge_conditions[$type] = $beforeTimestamp;
      }
      else {
        $purge_conditions[$type] = strtotime('-' . (int) $interval_days . ' days');
      }
    }

    // Make sure that templates that don't have a configuration can be manually added.
    if (!empty($types)) {
      foreach ($types as $type) {
        if (!isset($purge_conditions[$type])) {
          // If a before timestamp has not been provided, use the current time to purge everything of that type
          $purge_conditions[$type] = $beforeTimestamp ?? $this->time->getCurrentTime();
        }
      }
    }

    if (!empty($purge_conditions)) {
      $easy_email_storage = \Drupal::entityTypeManager()->getStorage('easy_email');
      $query = $easy_email_storage->getQuery();
      $or = $query->orConditionGroup();
      foreach ($purge_conditions as $type => $timestamp) {
        $and = $query->andConditionGroup();
        $and->condition('type', $type)
          ->condition('sent', $timestamp, '<');
        $or->condition($and);
      }
      $query->condition($or);

      $query->exists('sent')
        ->range(0, $limit);

      $results = $query->accessCheck(FALSE)->execute();
      foreach ($results as $email_id) {
        $email = $easy_email_storage->load($email_id);
        if ($email !== NULL) {
          $email->delete();
        }
      }
    }
  }

  protected function getPurgeConditions() {
    $purge_conditions = [];
    /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface[] $easy_email_types */
    $easy_email_types = $this->entityTypeManager->getStorage('easy_email_type')->loadMultiple();
    foreach ($easy_email_types as $easy_email_type) {
      if ($easy_email_type->getPurgeEmails()) {
        $purge_conditions[$easy_email_type->id()] = $easy_email_type->getPurgeInterval();
      }
    }
    return array_filter($purge_conditions);
  }

}
