<?php

namespace Drupal\easy_email;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Email entities.
 *
 * @ingroup easy_email
 */
class EasyEmailListBuilder extends EntityListBuilder {

  /**
   * @inheritDoc
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort($this->entityType->getKey('id'), 'DESC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['type'] = $this->t('Type');
    $header['recipient'] = $this->t('Recipients');
    $header['created'] = $this->t('Created');
    $header['sent'] = $this->t('Sent');

    $header['status'] = $this->t('Status');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $types = array_map(function($type) {
        return $type->label();
      },
      $this->getStorage()->getEmailTypeStorage()->loadMultiple()
    );

    /* @var $entity \Drupal\easy_email\Entity\EasyEmail */
    $row['id'] = $entity->id();

    $row['label'] = Link::createFromRoute(
      $entity->label(),
      'entity.easy_email.canonical',
      ['easy_email' => $entity->id()]
    );

    $row['type'] = !empty($types[$entity->bundle()]) ? $types[$entity->bundle()] : '';

    $recipients = $entity->getCombinedRecipientAddresses();
    $row['recipient'] = !empty($recipients) ? implode(', ', $recipients) : '';

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $row['created'] = $date_formatter->format($entity->getCreatedTime(), 'short');
    $row['sent'] = $entity->isSent() ? $date_formatter->format($entity->getSentTime(), 'short') : '';
    $row['status'] = $entity->isSent() ? $this->t('Sent') : $this->t('Unsent');

    return $row + parent::buildRow($entity);
  }

  /**
   * @inheritDoc
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations =  parent::getDefaultOperations($entity);
    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => -10,
      'url' => $this->ensureDestination($entity->toUrl('canonical')),
    ];
    return $operations;
  }


}
