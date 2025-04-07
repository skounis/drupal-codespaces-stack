<?php

namespace Drupal\dashboard\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Dashboard form.
 *
 * @property \Drupal\dashboard\DashboardInterface $entity
 */
class DashboardForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the dashboard.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\dashboard\Entity\Dashboard::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => !$this->entity->isNew() ? $this->entity->status() : TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the dashboard.'),
    ];

    $form['weight'] = [
      '#type' => 'value',
      '#value' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Before saving, we want to ensure it's not replacing the current default
    // dashboard.
    if ($this->entity->isNew()) {
      $storage = $this->entityTypeManager->getStorage('dashboard');
      $entity_query = $storage->getQuery();
      $max_weight_dashboard_id = $entity_query->sort('weight', 'DESC')
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
      if (!empty($max_weight_dashboard_id)) {
        $max_weight_dashboard = $storage->load(reset($max_weight_dashboard_id));
        $this->entity->setWeight($max_weight_dashboard->getWeight() + 1);
      }
    }

    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new dashboard %label.', $message_args)
      : $this->t('Updated dashboard %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
