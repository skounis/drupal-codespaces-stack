<?php

namespace Drupal\yoast_seo\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the settings form for the Real-Time SEO module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   A module handler.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user
  ) {
    parent::__construct($config_factory, $typedConfigManager);

    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'yoast_seo_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return string[]
   */
  public function getEditableConfigNames() {
    return ['yoast_seo.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('yoast_seo.settings');
    $xmlsitemap_enabled = $this->moduleHandler->moduleExists('xmlsitemap');
    $simple_sitemap_enabled = $this->moduleHandler->moduleExists('simple_sitemap');

    // Check if a sitemap module is installed and enabled.
    if ($xmlsitemap_enabled && $simple_sitemap_enabled) {
      // Discourage users from enabling both sitemap modules as they
      // might interfere.
      $xmlsitemap_description = $this->t('It looks like you have both the XML Sitemap and Simple XML Sitemap module enabled. Please uninstall one of them as they could interfere with each other.');
    }
    elseif ($xmlsitemap_enabled) {
      // Inform the user about altering the XML Sitemap configuration on the
      // module configuration page if they have access to do so.
      if ($this->currentUser->hasPermission('administer xmlsitemap')) {
        $xmlsitemap_description = $this->t(
          'You can configure the XML Sitemap settings at the @url.',
          [
            '@url' => Link::fromTextAndUrl(
              $this->t('configuration page'),
              Url::fromRoute('xmlsitemap.admin_search')
            )->toString(),
          ]
        );
      }
      else {
        $xmlsitemap_description = $this->t('You do not have the permission to administer the XML Sitemap.');
      }
    }
    elseif ($this->moduleHandler->moduleExists('simple_sitemap')) {
      // Inform the user about altering the XML Sitemap configuration on the
      // module configuration page if they have access to do so.
      if ($this->currentUser->hasPermission('administer simple_sitemap')) {
        $xmlsitemap_description = $this->t(
          'You can configure the Simple XML Sitemap settings at the @url.',
          [
            '@url' => Link::fromTextAndUrl(
              $this->t('configuration page'),
              Url::fromRoute('simple_sitemap.settings')
            )->toString(),
          ]
        );
      }
      else {
        $xmlsitemap_description = $this->t('You do not have the permission to administer the Simple XML Sitemap.');
      }
    }
    else {
      // XML Sitemap is not enabled, inform the user they should think about
      // installing and enabling it.
      $xmlsitemap_description = $this->t(
        'You currently do not have a sitemap module enabled. We strongly recommend you to install a sitemap module. You can download the <a href="@project1-url">@project1-name</a> or <a href="@project2-url">@project2-name</a> module to use as sitemap generator.',
        [
          '@project1-url' => 'https://www.drupal.org/project/simple_sitemap',
          '@project1-name' => 'Simple Sitemap',
          '@project2-url' => 'https://www.drupal.org/project/xmlsitemap',
          '@project2-name' => 'XML Sitemap',
        ]
      );
    }

    $form['xmlsitemap'] = [
      '#type' => 'details',
      '#title' => $this->t('Sitemap'),
      '#markup' => $xmlsitemap_description,
      '#open' => TRUE,
    ];

    // Inform the user about altering the Metatag configuration on the module
    // configuration page if they have access to do so.
    // We do not check if the module is enabled since it is our dependency.
    if ($this->currentUser->hasPermission('administer meta tags')) {
      $metatag_description = $this->t(
        'You can configure and override the Metatag title & description default settings at the @url.',
        [
          '@url' => Link::fromTextAndUrl(
            $this->t('Metatag configuration page'),
            Url::fromRoute('entity.metatag_defaults.collection')
          )->toString(),
        ]
      );
    }
    else {
      $metatag_description = $this->t('You currently do not have the permission to administer Metatag.');
    }

    $form['metatag'] = [
      '#type' => 'details',
      '#title' => $this->t('Configure Metatag default templates'),
      '#markup' => $metatag_description,
      '#open' => TRUE,
    ];

    $form['auto_refresh'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto refresh'),
      '#open' => TRUE,
    ];
    $form['auto_refresh']['auto_refresh_seo_result'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable auto refresh of the Real Time SEO widget result'),
      '#description' => $this->t('You can use this setting to enable auto refreshing of the Real Time SEO widget results after a value of a form field is changed and a field focus is lost. Please note that this may cause UI delays, waiting for e.g. the Metatag values to become available to calculate the score.'),
      '#default_value' => $config->get('auto_refresh_seo_result'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    parent::submitForm($form, $form_state);

    $this->config('yoast_seo.settings')
      ->set('auto_refresh_seo_result', $form_state->getValue('auto_refresh_seo_result'))
      ->save();
  }

}
