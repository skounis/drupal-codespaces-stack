<?php

namespace Drupal\symfony_mailer_lite\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer_lite\Entity\Transport;

/**
 * Mailer transport add form.
 */
class TransportAddForm extends TransportForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL) {
    $this->entity->setPluginId($plugin_id);
    $definition = $this->entity->getPlugin()->getPluginDefinition();
    $this->entity->set('label', $definition['label']);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // If there is no default transport, make this the default.
    if (!Transport::loadDefault()) {
      $this->entity->setAsDefault();
    }

    $form_state->setRedirect('entity.symfony_mailer_lite_transport.collection');
    return parent::save($form, $form_state);
  }

}
