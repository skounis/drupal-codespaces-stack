<?php

namespace Drupal\symfony_mailer_lite\Plugin\SymfonyMailerLite\Transport;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;

/**
 * Defines the native Mail Transport plug-in.
 *
 * @SymfonyMailerLiteTransport(
 *   id = "sendmail",
 *   label = @Translation("Sendmail"),
 *   description = @Translation("Use the local sendmail binary to send emails."),
 * )
 */
class SendmailTransport extends TransportBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'query' => ['command' => '/usr/sbin/sendmail -bs']
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['command'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sendmail Command'),
      '#default_value' => $this->configuration['query']['command'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['query']['command'] = $form_state->getValue('command');
  }

}
