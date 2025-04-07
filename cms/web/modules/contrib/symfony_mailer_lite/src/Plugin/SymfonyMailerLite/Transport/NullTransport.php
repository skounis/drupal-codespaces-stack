<?php

namespace Drupal\symfony_mailer_lite\Plugin\SymfonyMailerLite\Transport;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the null Mail Transport plug-in.
 *
 * @SymfonyMailerLiteTransport(
 *   id = "null",
 *   label = @Translation("Null"),
 *   description = @Translation("Disable delivery of messages."),
 * )
 */
class NullTransport extends TransportBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
