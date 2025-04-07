<?php

namespace Drupal\symfony_mailer_lite\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mailer transport edit form.
 */
class TransportForm extends EntityForm {

  /**
   * The transport plugin being configured.
   *
   * @var \Drupal\symfony_mailer_lite\TransportPluginInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->plugin = $this->entity->getPlugin();
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $transport = $this->entity;

    $definition = $transport->getPlugin()->getPluginDefinition();
    $form['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#disabled' => TRUE,
      '#default_value' => $definition['label'],
      '#description' => $definition['description'] ?? '',
    ];

    $form['warning'] = [
      '#markup' => $definition['warning'] ?? '',
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $transport->label(),
      '#description' => $this->t("Label for the Transport."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $transport->id(),
      '#machine_name' => [
        'exists' => '\Drupal\symfony_mailer_lite\Entity\Transport::load',
        'replace_pattern' => '[^a-z0-9_.]+',
        'source' => ['label'],
      ],
      '#required' => TRUE,
      '#disabled' => !$transport->isNew(),
    ];

    $form += $this->plugin->buildConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->plugin->submitConfigurationForm($form, $form_state);
    $this->messenger()->addMessage($this->t('The transport configuration has been saved.'));
  }

}
