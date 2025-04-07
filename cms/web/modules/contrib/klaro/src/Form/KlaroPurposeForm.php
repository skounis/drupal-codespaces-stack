<?php

namespace Drupal\klaro\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\klaro\Utility\KlaroHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an add form for a Klaro! purpose.
 *
 * @internal
 */
class KlaroPurposeForm extends EntityForm {

  /**
   * The cache data service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheData;

  /**
   * The Klaro! helper service.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $klaroHelper;

  /**
   * Constructs an ExampleForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Drupal\klaro\Utility\KlaroHelper $klaro_helper
   *   The Klaro! helper service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, KlaroHelper $klaro_helper) {
    $this->entityTypeManager = $entityTypeManager;
    $this->klaroHelper = $klaro_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('klaro.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\klaro\KlaroPurposeInterface $purpose */
    $purpose = $this->entity;
    $form = parent::form($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label', [], ['context' => 'klaro']),
      '#maxlength' => 255,
      '#default_value' => $purpose->label(),
      '#description' => $this->t("The label for the Klaro! purpose. The label will appear on the <em>Klaro! consent manager</em> modal.", [], ['context' => 'klaro']),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $purpose->id(),
      '#description' => $this->t('A unique machine-readable name for this Klaro! purpose.', [], ['context' => 'klaro']),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'source' => ['label'],
      ],
      '#disabled' => !$purpose->isNew(),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description', [], ['context' => 'klaro']),
      '#default_value' => $purpose->description(),
      '#description' => $this->t('An optional description for the Klaro! purpose. The description will appear on the <em>Klaro! consent manager</em> modal.', [], ['context' => 'klaro']),
      '#required' => FALSE,
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#min' => -99,
      '#max' => 99,
      '#default_value' => $purpose->weight(),
      '#title' => $this->t('Weight', [], ['context' => 'klaro']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\klaro\KlaroPurposeInterface $purpose */
    $purpose = $this->entity;

    $status = $purpose->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The Klaro! purpose %label has been created.', [
        '%label' => $purpose->label(),
      ], ['context' => 'klaro']));
    }
    else {
      $this->messenger()->addMessage($this->t('The Klaro! purpose %label has been updated.', [
        '%label' => $purpose->label(),
      ], ['context' => 'klaro']));
    }

    $form_state->setRedirect('entity.klaro_purpose.collection');

    return $status;
  }

  /**
   * Helper function to check if a Klaro! purpose configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('klaro_purpose')->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

}
