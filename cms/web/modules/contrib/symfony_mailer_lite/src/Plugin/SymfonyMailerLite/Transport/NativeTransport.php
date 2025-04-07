<?php

namespace Drupal\symfony_mailer_lite\Plugin\SymfonyMailerLite\Transport;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the native Transport plug-in.
 *
 * @SymfonyMailerLiteTransport(
 *   id = "native",
 *   label = @Translation("Native"),
 *   description = @Translation("Use the sendmail binary and options configured in the sendmail_path setting of php.ini."),
 *   warning = @Translation("<b>Note:</b> This transport uses the sendmail binary and options configured in the sendmail_path setting of php.ini. To troubleshoot email sending issues, please refer to your PHP settings."),
 * )
 */
class NativeTransport extends TransportBase {

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
