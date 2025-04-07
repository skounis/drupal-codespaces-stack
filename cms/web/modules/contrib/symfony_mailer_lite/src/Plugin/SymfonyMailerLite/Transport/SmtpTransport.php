<?php

namespace Drupal\symfony_mailer_lite\Plugin\SymfonyMailerLite\Transport;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the SMTP Mail Transport plug-in.
 *
 * @SymfonyMailerLiteTransport(
 *   id = "smtp",
 *   label = @Translation("SMTP"),
 *   description = @Translation("Use an SMTP server to send emails."),
 * )
 */
class SmtpTransport extends TransportBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'user' => '',
      'pass' => '',
      'host' => '',
      'port' => '',
      'query' => [
        'verify_peer' => TRUE,
        'local_domain' => '',
        'restart_threshold' => NULL,
        'restart_threshold_sleep' => NULL,
        'ping_threshold' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User name'),
      '#default_value' => $this->configuration['user'],
      '#description' => $this->t('User name to log in.'),
    ];

    // By default, keep the existing password except for a new transport
    // (which has empty host).
    $new = empty($this->configuration['host']);
    $form['change_pass'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Change password'),
      '#default_value' => $new,
      '#access' => !$new,
      '#description' => $this->t('Your password is stored; select to change it.'),
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $this->configuration['pass'],
      '#description' => $this->t('Password to log in.'),
      '#states' => [
        'visible' => [
          ':input[name="change_pass"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host name'),
      '#default_value' => $this->configuration['host'],
      '#description' => $this->t('SMTP host name.'),
      '#required' => TRUE,
    ];

    $form['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#default_value' => $this->configuration['port'],
      '#description' => $this->t('SMTP port.'),
      '#min' => 0,
      '#max' => 65535,
    ];

    $form['query']['verify_peer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Perform TLS peer verification'),
      '#description' => $this->t('This is recommended for security reasons, however it can be useful to disable it while developing or when using a self-signed certificate.'),
      '#default_value' => $this->configuration['query']['verify_peer'],
    ];

    $form['advanced_options'] = [
      '#type' => 'details',
      '#title' => 'Advanced options',
    ];

    $form['advanced_options']['local_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local domain'),
      '#default_value' => $this->configuration['query']['local_domain'],
      '#description' => $this->t('The domain name to use in HELO command.'),
    ];

    $form['advanced_options']['restart_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Restart threshold'),
      '#default_value' => $this->configuration['query']['restart_threshold'],
      '#description' => $this->t('The maximum number of messages to send before re-starting the transport.'),
      '#min' => 0,
    ];

    $form['advanced_options']['restart_threshold_sleep'] = [
      '#type' => 'number',
      '#title' => $this->t('Restart threshold sleep'),
      '#default_value' => $this->configuration['query']['restart_threshold_sleep'],
      '#description' => $this->t('The number of seconds to sleep between stopping and re-starting the transport.'),
      '#min' => 0,
    ];

    $form['advanced_options']['ping_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Ping threshold'),
      '#default_value' => $this->configuration['query']['ping_threshold'],
      '#description' => $this->t('The minimum number of seconds between two messages required to ping the server.'),
      '#min' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['user'] = $form_state->getValue('user');
    if (!empty($form_state->getValue('change_pass'))) {
      $this->configuration['pass'] = $form_state->getValue('pass');
    }
    $this->configuration['host'] = $form_state->getValue('host');
    $this->configuration['port'] = $form_state->getValue('port');
    $this->configuration['query']['verify_peer'] = $form_state->getValue('verify_peer');
    $this->configuration['query']['local_domain'] = $form_state->getValue('local_domain');
    $this->configuration['query']['restart_threshold'] = $form_state->getValue('restart_threshold');
    $this->configuration['query']['restart_threshold_sleep'] = $form_state->getValue('restart_threshold_sleep');
    $this->configuration['query']['ping_threshold'] = $form_state->getValue('ping_threshold');
  }

}
