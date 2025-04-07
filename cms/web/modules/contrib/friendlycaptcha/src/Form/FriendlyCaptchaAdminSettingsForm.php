<?php

namespace Drupal\friendlycaptcha\Form;

use Drupal\Core\Asset\LibrariesDirectoryFileFinder;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Friendly Captcha settings for this site.
 */
class FriendlyCaptchaAdminSettingsForm extends ConfigFormBase {

  /**
   * The library file finder object.
   *
   * @var \Drupal\Core\Asset\LibrariesDirectoryFileFinder
   */
  protected $libraryFileFinder;

  /**
   * {@inheritdoc}
   */
  public function __construct(LibrariesDirectoryFileFinder $libraryFileFinder) {
    $this->libraryFileFinder = $libraryFileFinder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('library.libraries_directory_file_finder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'friendlycaptcha_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['friendlycaptcha.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('friendlycaptcha.settings');

    $localEndpoint = $config->get('api_endpoint') === 'local';
    if ($userInput = $form_state->getUserInput()) {
      $localEndpoint = $userInput['friendlycaptcha_api_endpoint'] === 'local';
    }

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API related settings'),
      '#open' => TRUE,
    ];

    $form['api']['friendlycaptcha_api_endpoint'] = [
      '#default_value' => $config->get('api_endpoint'),
      '#options' => [
        'global' => $this->t('Global API-Endpoint'),
        'eu' => $this->t('EU API-Endpoint'),
        'eu_fallback' => $this->t('EU API-Endpoint with fallback to Global APO-Endpoint if not available'),
        'local' => $this->t('This Drupal site (Local-Endpoint)'),
      ],
      '#description' => $this->t('Select the Friendly Captcha API endpoint for your site. Both, the EU API-Endpoint (and "with fallback to Global API-Endpoint if not available") require a Friendly Captcha Business or Enterprise plan. <a href=":url">See docs for further information.</a>', [':url' => 'https://docs.friendlycaptcha.com/#/eu_endpoint']),
      '#required' => TRUE,
      '#title' => $this->t('API endpoint'),
      '#type' => 'select',
    ];

    $form['api']['friendlycaptcha_site_key'] = [
      '#default_value' => $config->get('site_key'),
      '#description' => $this->t('The site key given to you when you <a href=":url">register for Friendly Captcha</a>.', [':url' => 'https://app.friendlycaptcha.com/account']),
      '#maxlength' => 64,
      '#required' => !$localEndpoint,
      '#title' => $this->t('Site key'),
      '#type' => 'textfield',
      '#states' => [
        'invisible' => [
          ':input[name="friendlycaptcha_api_endpoint"]' => ['value' => 'local'],
        ],
        'optional' => [
          ':input[name="friendlycaptcha_api_endpoint"]' => ['value' => 'local'],
        ],
      ],
    ];

    $form['api']['friendlycaptcha_api_key'] = [
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('The API key given to you when you <a href=":url">register for Friendly Captcha</a>.', [':url' => 'https://www.google.com/friendlycaptcha/admin']),
      '#maxlength' => 128,
      '#required' => !$localEndpoint,
      '#title' => $this->t('API key'),
      '#type' => 'textfield',
      '#states' => [
        'invisible' => [
          ':input[name="friendlycaptcha_api_endpoint"]' => ['value' => 'local'],
        ],
        'optional' => [
          ':input[name="friendlycaptcha_api_endpoint"]' => ['value' => 'local'],
        ],
      ],
    ];

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];

    $form['general']['enable_validation_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable logging'),
      '#description' => $this->t('If enabled, all failed validation attempts and other miscellaneous things are logged. (e.g. an invalid captcha solution). This can be useful for debugging purposes.'),
      '#default_value' => $config->get('enable_validation_logging'),
    ];

    if (!$this->libraryFileFinder->find('friendly-challenge')) {
      $this->messenger()->addWarning(_friendlycaptcha_get_library_missing_message());
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // If we are using the local endpoint, we need to fill these configs with
    // dummy values, as they are being used on captcha generation:
    if ($form_state->getValue('friendlycaptcha_api_endpoint') === 'local' && $form_state->getValue('friendlycaptcha_site_key') === '') {
      $form_state->setValue('friendlycaptcha_site_key', 'ENTER VALID SITE KEY HERE');
      $form_state->setValue('friendlycaptcha_api_key', 'ENTER VALID API KEY HERE');
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('friendlycaptcha.settings');
    $config
      ->set('site_key', $form_state->getValue('friendlycaptcha_site_key'))
      ->set('api_key', $form_state->getValue('friendlycaptcha_api_key'))
      ->set('api_endpoint', $form_state->getValue('friendlycaptcha_api_endpoint'))
      ->set('enable_validation_logging', $form_state->getValue('enable_validation_logging'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
