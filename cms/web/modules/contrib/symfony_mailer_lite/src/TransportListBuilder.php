<?php

namespace Drupal\symfony_mailer_lite;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of mailer transport entities.
 */
class TransportListBuilder extends ConfigEntityListBuilder {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new TransportListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FormBuilderInterface $form_builder) {
    parent::__construct($entity_type, $storage);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'plugin' => $this->t('Type'),
      'label' => $this->t('Label'),
      'default' => '',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $definition = $entity->getPlugin()->getPluginDefinition();
    $row['label'] = $definition['label'];
    $row['plugin'] = $entity->label();
    $row['default'] = $entity->isDefault() ? $this->t('Default') : '';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // Add op to set default to all non-default transports.
    if (!$entity->isDefault()) {
      $operations['default'] = [
        'title' => $this->t('Set as default'),
        'url' => Url::fromRoute('entity.symfony_mailer_lite_transport.set_default', [
          'symfony_mailer_lite_transport' => $entity->id(),
        ]),
        'weight' => 50,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['transport_add_form'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $build['transport_add_form'] += $this->formBuilder->getForm('Drupal\symfony_mailer_lite\Form\TransportAddButtonForm');
    $build['transport_table'] = parent::render();
    return $build;
  }

}
