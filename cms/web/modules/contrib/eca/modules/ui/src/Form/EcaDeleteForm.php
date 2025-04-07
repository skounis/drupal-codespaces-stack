<?php

namespace Drupal\eca_ui\Form;

use Drupal\Core\Entity\EntityDeleteForm;

/**
 * Creates a form to delete an eca config entity.
 */
class EcaDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Deleting ECA model %label', ['%label' => $this->entity->label()]);
  }

}
