<?php

namespace Drupal\klaro\Controller;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\klaro\Utility\KlaroHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Klaro! apps.
 */
class KlaroAppListBuilder extends DraggableListBuilder {

  /**
   * The available purposes.
   *
   * @var array
   */
  protected $availablePurposes;

  /**
   * {@inheritdoc}
   */
  protected $limit = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'klaro_app_list';
  }

  /**
   * Constructs a new KlaroAppListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\klaro\Utility\KlaroHelper $helper
   *   The klaro helper.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, KlaroHelper $helper) {
    parent::__construct($entity_type, $storage);
    $this->availablePurposes = $helper->optionPurposes();
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('klaro.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['status'] = $this->t('Status', [], ['context' => 'klaro']);
    $header['label'] = $this->t('Service', [], ['context' => 'klaro']);
    $header['purposes'] = $this->t('Purposes', [], ['context' => 'klaro']);
    $header['default'] = $this->t('Toggled by default', [], ['context' => 'klaro']);
    $header['required'] = $this->t('Required', [], ['context' => 'klaro']);
    $header['opt_out'] = $this->t('Opt out', [], ['context' => 'klaro']);
    $header['only_once'] = $this->t('Only once', [], ['context' => 'klaro']);
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\klaro\KlaroAppInterface $entity */
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled', [], ['context' => 'klaro']) : $this->t('Disabled', [], ['context' => 'klaro']);
    $row['label'] = $entity->label();
    $row['purposes']['#theme'] = 'item_list';
    foreach ($this->listPurposes($entity->purposes()) as $purpose) {
      $row['purposes']['#items'][] = $purpose;
    }
    $row['default']['#markup'] = $entity->isDefault() ? $this->t('Yes', [], ['context' => 'klaro']) : $this->t('No', [], ['context' => 'klaro']);
    $row['required']['#markup'] = $entity->isRequired() ? $this->t('Yes', [], ['context' => 'klaro']) : $this->t('No', [], ['context' => 'klaro']);
    $row['opt_out']['#markup'] = $entity->isOptOut() ? $this->t('Yes', [], ['context' => 'klaro']) : $this->t('No', [], ['context' => 'klaro']);
    $row['only_once']['#markup'] = $entity->isOnlyOnce() ? $this->t('Yes', [], ['context' => 'klaro']) : $this->t('No', [], ['context' => 'klaro']);

    return $row + parent::buildRow($entity);
  }

  /**
   * Build an array of purpose labels.
   *
   * @param array $purposes
   *   The purpose ids.
   *
   * @return array
   *   The purpose labels.
   */
  private function listPurposes(array $purposes = []): array {
    $list = [];

    foreach ($purposes as $key) {
      if (isset($this->availablePurposes[$key])) {
        $list[] = $this->availablePurposes[$key];
      }
      else {
        $list[] = $key;
      }
    }

    return $list;
  }

}
