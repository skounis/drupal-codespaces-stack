<?php

namespace Drupal\klaro\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\klaro\Utility\KlaroHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General settings form for the Klaro! consent manager.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal\klaro\Utility\KlaroHelper.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $klaro;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The constructor.
   *
   * @param Drupal\klaro\Utility\KlaroHelper $klaro
   *   The Klaro Helper.
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManagerInterface.
   */
  public function __construct(KlaroHelper $klaro, EntityTypeManagerInterface $entity_type_manager) {
    $this->klaro = $klaro;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('klaro.helper'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'klaro_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'klaro.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('klaro.settings');

    if (!$this->klaro->hasLibraryFiles() && !$this->klaro->hasDeprecatedLibraryFiles()) {
      $form['lib_error'] = [
        'message' => [
          '#theme' => 'status_messages',
          '#message_list' => [
            'error' => [
              $this->t('The klaro-js library is not found at libraries folder, please read the install instructions in the README of the Klaro! drupal module.'),
            ],
          ],
          '#status_headings' => [
            'error' => $this
              ->t('Library not found'),
          ],
        ],
      ];
    }

    $role_with_permission = FALSE;
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $roleName => $role) {
      if ($roleName != 'administrator' && $role->hasPermission('use klaro')) {
        $role_with_permission = TRUE;
      }
    }

    if (!$role_with_permission) {
      $form['role_hint'] = [
        'message' => [
          '#theme' => 'status_messages',
          '#message_list' => [
            'warning' => [
              $this->t('Currently only the administrator role has the "use klaro" permission. To let the visitors of your site manage their consents with Klaro!, add the "use klaro" permission to role "anonymous".'),
            ],
          ],
          '#status_headings' => [
            'warning' => $this
              ->t('No permissions set'),
          ],
        ],
      ];
    }

    $form['dialog_info'] = [
      'message' => [
        '#theme' => 'status_messages',
        '#message_list' => [
          'info' => [
            $this->t('The Klaro! <strong>notice dialog</strong> briefly informs about the use of external services or cookies. The <strong>consent dialog modal</strong> is be used to manage consents for services or purposes.', [], ['context' => 'klaro']),
          ],
        ],
        '#status_headings' => [
          'info' => $this
            ->t('The two Klaro! dialog forms:'),
        ],
      ],
    ];

    $form['vertical_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Library settings', [], ['context' => 'klaro']),
    ];

    // General settings.
    $form['general_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('General', [], ['context' => 'klaro']),
      '#description' => $this->t('Configure behavior and options for the Klaro! element.', [], ['context' => 'klaro']),
      '#group' => 'vertical_tabs',
    ];

    $form['general_settings']['dialog'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Consent', [], ['context' => 'klaro']),
      '#description' => $this->t('Some national regulations require a close button for the consent. It can be enabled by the <em>Add close button to the Klaro! dialog</em> option.', [], ['context' => 'klaro']),
      '#tree' => FALSE,
    ];

    $form['general_settings']['dialog']['dialog_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Klaro! Dialog Mode', [], ['context' => 'klaro']),
      '#options' => [
        'silent' => $this->t('Silent (no dialog, only modify attribute and block sources)', [], ['context' => 'klaro']),
        'notice' => $this->t('Notice dialog', [], ['context' => 'klaro']),
        'notice_modal' => $this->t('Notice dialog as modal', [], ['context' => 'klaro']),
        'manager' => $this->t('Consent dialog modal', [], ['context' => 'klaro']),
      ],
      '#description' => $this->t('If no cookies or external services are used, the dialog can be hidden (default). For the different modes, see <a href="@website">online documentation</a>.', ['@website' => 'https://www.drupal.org/node/3487236'], ['context' => 'klaro']),
      '#default_value' => $config->get('dialog_mode'),
    ];

    $form['general_settings']['dialog']['show_toggle_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Toggle Button', [], ['context' => 'klaro']),
      '#description' => $this->t('Adds a flying button to open the consent dialog. Otherwise a <a href="@website">menu link</a> can be used.', ['@website' => 'https://www.drupal.org/node/3485316'], ['context' => 'klaro']),
      '#default_value' => $config->get('show_toggle_button'),
    ];

    $form['general_settings']['dialog']['enable_autofocus'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autofocus Klaro! Dialog', [], ['context' => 'klaro']),
      '#description' => $this->t('If activated, the open Klaro! dialog is automatically focused after loading.', ['context' => 'klaro']),
      '#default_value' => $config->get('library.auto_focus'),
    ];

    $form['general_settings']['apps'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Services', [], ['context' => 'klaro']),
      '#tree' => TRUE,
    ];
    $form['general_settings']['apps']['group_by_purpose'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group by purpose', [], ['context' => 'klaro']),
      '#description' => $this->t('Allow the user to enable or disable entire groups of services at once. This also reduces the space taken up by the consent dialog modal, which is important especially for websites that use many third-party applications.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.group_by_purpose'),
    ];
    $form['general_settings']['apps']['process_descriptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verbose service descriptions'),
      '#description' => $this->t('If enabled, all Klaro! service descriptions will be processed. As for now they will get extended by the privacy policy url and the info url. If you enable "Allow HTML in texts" at settings->styling the links will be formatted as anchors, otherwise they can only be displayed as text and are not clickable.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('process_descriptions'),
    ];

    $form['general_settings']['buttons'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Buttons', [], ['context' => 'klaro']),
      '#tree' => TRUE,
    ];
    $form['general_settings']['buttons']['accept_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accept all', [], ['context' => 'klaro']),
      '#description' => $this->t('Adds a button <em>Accept all</em> to the consent dialog modal and changes the behavior of the button <em>Accept</em> of the notice dialog. If clicked, <em>all</em> services are accepted, instead of only required services and those enabled by default.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.accept_all'),
    ];
    $form['general_settings']['buttons']['decline_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decline all', [], ['context' => 'klaro']),
      '#description' => $this->t('Adds a button <em>decline</em> to notice dialog and consent dialog modal.', [], ['context' => 'klaro']),
      '#default_value' => !$config->get('library.hide_decline_all'),
    ];
    $form['general_settings']['buttons']['learn_more'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to open consent dialog', [], ['context' => 'klaro']),
      '#description' => $this->t('Show a link in the notice dialog that opens the consent dialog modal.', [], ['context' => 'klaro']),
      '#default_value' => !$config->get('library.hide_learn_more'),
    ];
    $form['general_settings']['buttons']['learn_more_as_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display link as a button', [], ['context' => 'klaro']),
      '#description' => $this->t('Displays the link (see above) in the notice dialog in button style.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.learn_more_as_button'),
      '#states' => [
        'visible' => [':input[name="buttons[learn_more]"]' => ['checked' => TRUE]],
      ],
    ];
    $form['general_settings']['buttons']['show_close_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add close button to the Klaro! dialog', [], ['context' => 'klaro']),
      '#description' => $this->t('Closes the dialog and declines all consents. Mandatory in some countries for consent dialogs.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('show_close_button'),
      '#attributes' => [
        'name' => 'buttons[show_close_button]',
      ],
    ];

    // Storage settings.
    $form['storage_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Storage', [], ['context' => 'klaro']),
      '#group' => 'vertical_tabs',
    ];

    $form['storage_settings']['storage_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Storage type', [], ['context' => 'klaro']),
      '#description' => $this->t('How Klaro! should store the preferences of the user.', [], ['context' => 'klaro']),
      '#required' => TRUE,
      '#options' => [
        'cookie' => $this->t('Cookie', [], ['context' => 'klaro']),
        'localStorage' => $this->t('Local storage', [], ['context' => 'klaro']),
      ],
      '#default_value' => $config->get('library.storage_method'),
    ];

    $form['storage_settings']['cookie_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie name', [], ['context' => 'klaro']),
      '#description' => $this->t('Customize the name of the cookie that Klaro! uses for storing user consent decisions.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.cookie_name'),
      '#states' => [
        'visible' => [':input[name="storage_method"]' => ['value' => 'cookie']],
      ],
    ];
    $form['storage_settings']['cookie_expires_after_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Cookie expires after', [], ['context' => 'klaro']),
      '#description' => $this->t('Set a custom expiration time for the Klaro! cookie.', [], ['context' => 'klaro']),
      '#min' => 0,
      '#max' => 365,
      '#step' => 1,
      '#field_suffix' => $this->t('days', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.cookie_expires_after_days'),
      '#states' => [
        'visible' => [':input[name="storage_method"]' => ['value' => 'cookie']],
      ],
    ];
    $form['storage_settings']['cookie_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie domain', [], ['context' => 'klaro']),
      '#description' => $this->t('Set cookie domain for the consent manager itself. Use this to get consent once for multiple matching domains. If undefined, Klaro! will use the current domain.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.cookie_domain'),
      '#states' => [
        'visible' => [':input[name="storage_method"]' => ['value' => 'cookie']],
      ],
    ];

    // Consent settings.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced', [], ['context' => 'klaro']),
      '#description' => $this->t('Configure advanced settings.', [], ['context' => 'klaro']),
      '#group' => 'vertical_tabs',
    ];

    $form['advanced']['deletable_cookie_domains'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Matching cookie domains', [], ['context' => 'klaro']),
      '#description' => $this->t('Enter one domain per line for cookie deletion. Leave empty to delete for the current domain of the visitor only.', [], ['context' => 'klaro']),
      '#default_value' => implode("\n", $config->get('deletable_cookie_domains')),
    ];

    $form['advanced']['exclude_urls'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Disable Klaro! and block attributed resources on following url patterns', [], ['context' => 'klaro']),
      '#description' => $this->t('Enter one regular expression per line without delimiters, i.e  \/admin\/ will match all paths that contain /admin/ while i.e ^\/en will match all routes that start with /en. On these paths all resources remain blocked and Klaro! will be disabled.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('exclude_urls') ? implode("\n", $config->get('exclude_urls')) : '',
    ];

    $form['advanced']['disable_urls'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Disable Klaro! element and dont block attributed resources on following url patterns', [], ['context' => 'klaro']),
      '#description' => $this->t('Enter one regular expression per line without delimiters, i.e  \/admin\/ will match all paths that contain /admin/ while i.e ^\/en will match all routes that start with /en. On these paths no resources are blocked and Klaro! will be disabled.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('disable_urls') ? implode("\n", $config->get('disable_urls')) : '',
    ];

    // Blocking and logging unknown resources.
    $form['unknown_resources'] = [
      '#type' => 'details',
      '#title' => $this->t('Unknown resources'),
      '#description' => $this->t('During processing to decorate attributes (see <em>Automatic attribution</em>), this module can detect external resources and embedded external content without a matching service.', [], ['context' => 'klaro']),
      '#group' => 'vertical_tabs',
    ];

    $form['unknown_resources']['log_unknown_resources'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log unknown resources', [], ['context' => 'klaro']),
      '#description' => $this->t('Creates a notice in recent log messages whenever an unknown external resource is requested.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('log_unknown_resources'),
    ];

    $form['unknown_resources']['block_unknown'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block unknown external resources'),
      '#description' => $this->t('Matches and decorates resources that are external and did not match a configured service (works best if <em>Process final HTML</em> is activated; find it in tab <em>Automatic attribution</em>).', [], ['context' => 'klaro']),
      '#default_value' => $config->get('block_unknown'),
    ];

    $form['unknown_resources']['block_unknown_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label', [], ['context' => 'klaro']),
      '#description' => $this->t('Label of the service for external unknown resources.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('block_unknown_label'),
      '#states' => [
        'visible' => [
          ':input[name="block_unknown"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['unknown_resources']['block_unknown_description'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Description', [], ['context' => 'klaro']),
      '#description' => $this->t('Short description of the service for external unknown resources.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('block_unknown_description'),
      '#states' => [
        'visible' => [
          ':input[name="block_unknown"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Auto decorate settings.
    $form['auto_decorate'] = [
      '#type' => 'details',
      '#title' => $this->t('Automatic attribution'),
      '#description' => $this->t('To make Klaro! block resources, they need <a target="_blank" href="@website">special html attributes</a> which the klaro library expects you to add manually - this module can try to set them automatically. Please choose the processors to be activated.', ['@website' => "https://klaro.org/docs/getting-started"], ['context' => 'klaro']),
      '#group' => 'vertical_tabs',
    ];

    $form['auto_decorate']['js_alter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process js_alter', [], ['context' => 'klaro']),
      '#description' => $this->t('Matches and decorates script files added from libraries against the configured services.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('auto_decorate_js_alter'),
    ];
    $form['auto_decorate']['page_attachments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process page_attachments', [], ['context' => 'klaro']),
      '#description' => $this->t('Matches and decorates manually attached JS files against the configured services.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('auto_decorate_page_attachments'),
    ];
    $form['auto_decorate']['preprocess_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process preprocess_field', [], ['context' => 'klaro']),
      '#description' => $this->t('Matches and decorates iframes or oembeds from special field types (see README.md).', [], ['context' => 'klaro']),
      '#default_value' => $config->get('auto_decorate_preprocess_field'),
    ];
    $form['auto_decorate']['get_entity_thumbnail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Determine thumbnail for preview', [], ['context' => 'klaro']),
      '#description' => $this->t('While preprocessing fields try to determine thumbnail for preview.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('get_entity_thumbnail'),
    ];
    $form['auto_decorate']['final_html'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process final HTML', [], ['context' => 'klaro']),
      '#description' => $this->t('Adds contextual blocking to iframe, img, audio and video tags and adds attributes to all matching script tags that are not attributed yet. This feature is rather experimental - invalid or malformed html might lead to unknown behavior.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('auto_decorate_final_html'),
    ];

    // Inbuilt styling settings.
    $form['styling'] = [
      '#type' => 'details',
      '#title' => $this->t('Styling', [], ['context' => 'klaro']),
      '#description' => $this->t('Configure styling settings.', [], ['context' => 'klaro']),
      '#group' => 'vertical_tabs',
    ];

    $form['styling']['element_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Element ID', [], ['context' => 'klaro']),
      '#description' => $this->t('Specify the HTML CSS identifier for the Klaro! container.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.element_id'),
      '#required' => TRUE,
      // In theory, others are allowed, but we like to KISS and stay sane.
      '#pattern' => '[^0-9-][a-zA-Z0-9_-]*',
    ];

    $form['styling']['additional_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Additional CSS classes', [], ['context' => 'klaro']),
      '#description' => $this->t('Add custom classes separated by spaces to the Klaro! container, i.e. <code>custom-class-one custom-class-two</code>', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.additional_class'),
    ];

    $form['styling']['styles'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Klaro css variables', [], ['context' => 'klaro']),
      '#description' => $this->t('Override inbuilt klaro css variables separated by a comma, i.e. "light, top" to use the light theme and position the notice at the top, <a href="@website" target="_blank"> More infos </a>', ['@website' => 'https://github.com/klaro-org/klaro-js/blob/master/src/themes.js'], ['context' => 'klaro']),
      '#default_value' => $config->get('styles') ? implode(',', $config->get('styles')) : '',
    ];

    $form['styling']['show_notice_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show title in notice dialog.', [], ['context' => 'klaro']),
      '#description' => $this->t('Activate to display the title of the Klaro! notice dialog. Otherwise it will be visually hidden.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('show_notice_title'),
    ];

    $form['styling']['html_texts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow HTML in texts', [], ['context' => 'klaro']),
      '#description' => $this->t('Activating this will allow HTML in the descriptions of the services (e.g. for links). Use with care!', [], ['context' => 'klaro']),
      '#default_value' => $config->get('library.html_texts'),
    ];

    $form['styling']['override_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Adjust the UI to Drupal themes', [], ['context' => 'klaro']),
      '#description' => $this->t('Use a Drupal-like appearance for Klaro! UI elements. This module provides customized CSS styles for <em>Olivero</em>, <em>Claro</em> and <em>Gin</em>.', [], ['context' => 'klaro']),
      '#default_value' => $config->get('override_css'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('klaro.settings');

    $cookie_domains = array_map('trim', explode("\n", $form_state->getValue('deletable_cookie_domains')));
    $exclude_urls = array_map('trim', explode("\n", $form_state->getValue('exclude_urls')));
    $disable_urls = array_map('trim', explode("\n", $form_state->getValue('disable_urls')));
    $styles = array_map('trim', explode(",", $form_state->getValue('styles')));

    $config
      ->set('library.element_id', $form_state->getValue('element_id'))
      ->set('library.storage_method', $form_state->getValue('storage_method'))
      ->set('library.cookie_name', $form_state->getValue('cookie_name'))
      ->set('library.cookie_expires_after_days', $form_state->getValue('cookie_expires_after_days'))
      ->set('library.cookie_domain', $form_state->getValue('cookie_domain'))
      ->set('dialog_mode', $form_state->getValue('dialog_mode'))
      ->set('library.additional_class', $form_state->getValue('additional_class'))
      ->set('library.html_texts', $form_state->getValue('html_texts'))
      ->set('library.group_by_purpose', $form_state->getValue([
        'apps',
        'group_by_purpose',
      ]))
      ->set('library.accept_all', !!$form_state->getValue([
        'buttons',
        'accept_all',
      ]))
      ->set('library.hide_decline_all', !$form_state->getValue([
        'buttons',
        'decline_all',
      ]))
      ->set('library.hide_learn_more', !$form_state->getValue([
        'buttons',
        'learn_more',
      ]))
      ->set('library.learn_more_as_button', $form_state->getValue([
        'buttons',
        'learn_more_as_button',
      ]))
      ->set('library.auto_focus', $form_state->getValue('enable_autofocus'))
      ->set('show_toggle_button', $form_state->getValue('show_toggle_button'))
      ->set('show_close_button', $form_state->getValue([
        'buttons',
        'show_close_button',
      ]))
      ->set('block_unknown', $form_state->getValue('block_unknown'))
      ->set('log_unknown_resources', $form_state->getValue('log_unknown_resources'))
      ->set('block_unknown_label', $form_state->getValue('block_unknown_label'))
      ->set('block_unknown_description', $form_state->getValue('block_unknown_description'))
      ->set('auto_decorate_js_alter', $form_state->getValue('js_alter'))
      ->set('auto_decorate_page_attachments', $form_state->getValue('page_attachments'))
      ->set('auto_decorate_preprocess_field', $form_state->getValue('preprocess_field'))
      ->set('get_entity_thumbnail', $form_state->getValue('get_entity_thumbnail'))
      ->set('auto_decorate_final_html', $form_state->getValue('final_html'))
      ->set('deletable_cookie_domains', array_filter($cookie_domains))
      ->set('exclude_urls', array_filter($exclude_urls))
      ->set('disable_urls', array_filter($disable_urls))
      ->set('styles', $styles)
      ->set('show_notice_title', $form_state->getValue('show_notice_title'))
      ->set('override_css', $form_state->getValue('override_css'))
      ->set('process_descriptions', $form_state->getValue([
        'apps',
        'process_descriptions',
      ]));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
