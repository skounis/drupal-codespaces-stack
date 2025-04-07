<?php

namespace Drupal\klaro\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete a Klaro! app.
 */
class KlaroAppDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the Klaro! service "%name"?', [
      '%name' => $this->entity->label(),
    ], ['context' => 'klaro']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.klaro_app.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete', [], ['context' => 'klaro']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('Klaro! service %label has been deleted.', [
      '%label' => $this->entity->label(),
    ], ['context' => 'klaro']));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
