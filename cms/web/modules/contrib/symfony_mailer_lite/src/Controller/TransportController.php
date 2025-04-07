<?php

namespace Drupal\symfony_mailer_lite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\symfony_mailer_lite\TransportInterface;

class TransportController extends ControllerBase {

  /**
   * Sets the transport as the default.
   *
   * @param \Drupal\symfony_mailer_lite\TransportInterface $symfony_mailer_lite_transport
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the transport listing page.
   */
  public function setAsDefault(TransportInterface $symfony_mailer_lite_transport) {
    $symfony_mailer_lite_transport->setAsDefault();
    $this->messenger()->addStatus($this->t('The default transport is now %label.', ['%label' => $symfony_mailer_lite_transport->label()]));
    return $this->redirect('entity.symfony_mailer_lite_transport.collection');
  }

}
