<?php

namespace Drupal\symfony_mailer_lite\Plugin\SymfonyMailerLite\Transport;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Mailer\Transport;

/**
 * Defines the native Mail Transport plug-in.
 *
 * @SymfonyMailerLiteTransport(
 *   id = "dsn",
 *   label = @Translation("DSN"),
 *   description = @Translation("The DSN transport is a generic fallback and should only be used if there is no specific implementation available."),
 * )
 */
class DsnTransport extends TransportBase {

  const DOCS_URL = 'https://symfony.com/doc/current/mailer.html#transport-setup';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'dsn' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['dsn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DSN'),
      '#maxlength' => 255,
      '#default_value' => $this->configuration['dsn'],
      '#description' => $this->t('DSN for the Transport, see <a href=":docs">documentation</a>.', [':docs' => static::DOCS_URL]),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $dsn = $form_state->getValue('dsn');
    if (parse_url($dsn, PHP_URL_SCHEME) == 'sendmail') {
      // Don't allow bypassing of the checks done by the Sendmail transport.
      $form_state->setErrorByName('dsn', $this->t('Use the Sendmail transport.'));
    }

    try {
      Transport::fromDsn($dsn);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('dsn', $this->t('Invalid DSN: @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['dsn'] = $form_state->getValue('dsn');
  }

  /**
   * {@inheritdoc}
   */
  public function getDsn() {
    return $this->configuration['dsn'];
  }

}
