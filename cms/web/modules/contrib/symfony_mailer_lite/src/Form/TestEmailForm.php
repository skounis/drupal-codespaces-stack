<?php

namespace Drupal\symfony_mailer_lite\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\MailerHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Drupal Symfony Mailer Lite test email form.
 */
class TestEmailForm extends FormBase {

  /**
   * @var MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'symfony_mailer_lite_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['recipient'] = [
      '#title' => $this->t('Recipient'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->currentUser()->getEmail(),
      '#description' => $this->t('Recipient email address.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send test email'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->mailManager->mail(
      'symfony_mailer_lite',
      'test',
      $form_state->getValue(['recipient']),
      $this->languageManager->getDefaultLanguage()->getId()
    );
    $this->messenger()->addMessage(
      $this->t('An attempt has been made to send an e-mail to @email.', [
        '@email' => $form_state->getValue(['recipient'])
      ])
    );
  }

}
