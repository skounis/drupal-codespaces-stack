<?php

namespace Drupal\klaro\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\klaro\Utility\KlaroHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Provides an add form for a Klaro! app.
 *
 * @internal
 */
class KlaroAppForm extends EntityForm {

  /**
   * The Klaro! helper service.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $klaroHelper;

  /**
   * Constructs an ExampleForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Drupal\klaro\Utility\KlaroHelper $klaro_helper
   *   The Klaro! helper service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, KlaroHelper $klaro_helper) {
    $this->entityTypeManager = $entityTypeManager;
    $this->klaroHelper = $klaro_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('klaro.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\klaro\KlaroAppInterface $app */
    $app = $this->entity;
    $form = parent::form($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label', [], ['context' => 'klaro']),
      '#maxlength' => 255,
      '#default_value' => $app->label(),
      '#description' => $this->t("The label for the service. The label will appear on the <em>Klaro! consent manager</em> modal.", [], ['context' => 'klaro']),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $app->id(),
      '#description' => $this->t('A unique machine-readable name for this Klaro! service.', [], ['context' => 'klaro']),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'source' => ['label'],
      ],
      '#disabled' => !$app->isNew(),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description', [], ['context' => 'klaro']),
      '#default_value' => $app->description(),
      '#description' => $this->t('Describe this service. The text will appear on the <em>Klaro! consent manager</em> modal.', [], ['context' => 'klaro']),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled', [], ['context' => 'klaro']),
      '#default_value' => $app->isNew() ? FALSE : $app->status(),
    ];

    $form['klaro'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Settings', [], ['context' => 'klaro']),
    ];

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General', [], ['context' => 'klaro']),
      '#group' => 'klaro',
      '#required' => TRUE,
    ];

    $form['general']['purposes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Purposes', [], ['context' => 'klaro']),
      '#description' => $this->t('Which purposes does this service match best? This information will appear on the <em>consent dialog modal</em>.', [], ['context' => 'klaro']),
      '#required' => TRUE,
      '#options' => $this->klaroHelper->optionPurposes(),
      '#default_value' => $app->purposes(),
    ];

    $form['general']['default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Toggled by default', [], ['context' => 'klaro']),
      '#description' => $this->t('The default state of this Klaro! service. If checked, the service is pre-enabled on the <em>consent dialog modal</em>.', [], ['context' => 'klaro']),
      '#default_value' => $app->isDefault(),
    ];

    $form['general']['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required', [], ['context' => 'klaro']),
      '#description' => $this->t('The user will not be able to disable it on the <em>consent dialog modal</em>.', [], ['context' => 'klaro']),
      '#default_value' => $app->isRequired(),
    ];

    $form['general']['opt_out'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Opt out', [], ['context' => 'klaro']),
      '#description' => $this->t('Already load this Klaro! service, even before the user gives explicit consent.', [], ['context' => 'klaro']),
      '#default_value' => $app->isOptOut(),
    ];

    $form['general']['only_once'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only once', [], ['context' => 'klaro']),
      '#description' => $this->t('The Klaro! service will only be executed once. Regardless how often the user toggles it on and off.', [], ['context' => 'klaro']),
      '#default_value' => $app->isOnlyOnce(),
    ];

    $form['general']['contextual_consent_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Contextual Consent Only', [], ['context' => 'klaro']),
      '#description' => $this->t('This service is not activated when “Accept all” is clicked (only relevant if this button is activated in the module settings).', [], ['context' => 'klaro']),
      '#default_value' => $app->isContextualConsentOnly(),
    ];

    $form['general']['contextual_consent_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contextual Consent Text', [], ['context' => 'klaro']),
      '#description' => $this->t('This will be shown in the placeholder. Allowed tags: <em>a</em>, <em>em</em>, <em>strong</em>.', [], ['context' => 'klaro']),
      '#default_value' => $app->contextualConsentText(),
      '#element_validate' => [
        [get_class($this), 'validateXss'],
      ],
    ];

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('About', [], ['context' => 'klaro']),
      '#group' => 'klaro',
    ];

    $form['info']['info_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Website', [], ['context' => 'klaro']),
      '#description' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('The url where people can get general information about this service.', [], ['context' => 'klaro']),
          $this->t('You can enter an internal path such as %path or an external URL such as %url', [
            '%path' => 'internal:/node/add',
            '%url' => 'https://example.org',
          ], ['context' => 'klaro']),
        ],
      ],
      '#default_value' => $app->infoUrl(),
      '#placeholder' => 'https://',
      '#element_validate' => [
        [get_class($this), 'validateUrl'],
      ],
    ];

    $form['info']['privacy_policy_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Privacy policy', [], ['context' => 'klaro']),
      '#description' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('The url where people can read the privacy policies of using this service.', [], ['context' => 'klaro']),
          $this->t('You can enter an internal path such as %path or an external URL such as %url', [
            '%path' => 'internal:/node/add',
            '%url' => 'https://example.org',
          ], ['context' => 'klaro']),
        ],
      ],
      '#default_value' => $app->privacyPolicyUrl(),
      '#placeholder' => 'internal:/<front>',
      '#element_validate' => [
        [get_class($this), 'validateUrl'],
      ],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced', [], ['context' => 'klaro']),
      '#group' => 'klaro',
      '#tree' => FALSE,
    ];

    $form['advanced']['cookies_wrapper'] = [
      '#type' => 'item',
      '#title' => $this->t('Cookies', [], ['context' => 'klaro']),
      '#description' => [
        '#markup' => $this->t('Klaro! can only delete cookies if their names match the regular expression and both path and domain <em>match exactly</em>.', [], ['context' => 'klaro']),
        'options' => [
          '#theme' => 'item_list',
          '#items' => [
            $this->t('Regular expression to identify the cookie value.', [], ['context' => 'klaro']),
            $this->t('Leave the path empty to use "/".', [], ['context' => 'klaro']),
            $this->t('Leave domain empty to use the browser <code>location.host</code>.', [], ['context' => 'klaro']),
          ],
        ],
      ],
      '#description_display' => 'before',
    ];
    $form['advanced']['cookies_wrapper']['cookies'] = [
      '#type' => 'table',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#header' => [
        'regex' => $this->t('Regular expression', [], ['context' => 'klaro']),
        'path' => $this->t('Path', [], ['context' => 'klaro']),
        'domain' => $this->t('Domain', [], ['context' => 'klaro']),
        'delete' => $this->t('Operations', [], ['context' => 'klaro']),
      ],
      '#prefix' => '<div id="cookies-wrapper">',
      '#suffix' => '</div>',
    ];

    $cookies = $form_state->get('cookies') ?: ($form_state->getTriggeringElement() ? [] : $app->cookies());
    $form_state->set('cookies', $cookies);

    foreach ($cookies as $delta => $cookie) {
      $form['advanced']['cookies_wrapper']['cookies'][$delta]['regex'] = [
        '#type' => 'textfield',
        '#field_prefix' => '/',
        '#field_suffix' => '/i',
        '#title' => $this->t('Regular expression', [], ['context' => 'klaro']),
        '#title_display' => 'visually_hidden',
        '#default_value' => $cookie['regex'] ?? '',
        '#size' => NULL,
      ];

      $form['advanced']['cookies_wrapper']['cookies'][$delta]['path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Path', [], ['context' => 'klaro']),
        '#title_display' => 'visually_hidden',
        '#default_value' => $cookie['path'] ?? '',
        '#placeholder' => '/',
        '#size' => NULL,
      ];

      $form['advanced']['cookies_wrapper']['cookies'][$delta]['domain'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Domain', [], ['context' => 'klaro']),
        '#title_display' => 'visually_hidden',
        '#default_value' => $cookie['domain'] ?? '',
        '#placeholder' => 'location.host',
        '#size' => NULL,
      ];

      $form['advanced']['cookies_wrapper']['cookies'][$delta]['delete'] = [
        '#type' => 'submit',
        '#name' => "delete[$delta]",
        '#value' => $this->t('Remove', [], ['context' => 'klaro']),
        '#submit' => ['::ajaxCookieSubmit'],
        '#ajax' => [
          'callback' => '::returnCookies',
          'wrapper' => 'cookies-wrapper',
        ],
        '#attributes' => [
          'class' => [
            'button-action',
            'button--small',
          ],
        ],
        '#limit_validation_errors' => [],
      ];
    }

    $form['advanced']['cookies_wrapper']['cookies']['actions'] = [
      '#type' => 'actions',
    ];

    $form['advanced']['cookies_wrapper']['cookies']['actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add', [], ['context' => 'klaro']),
      '#submit' => ['::ajaxCookieSubmit'],
      '#ajax' => [
        'callback' => '::returnCookies',
        'wrapper' => 'cookies-wrapper',
      ],
      '#attributes' => [
        'title' => $this->t('Add another cookie information', [], ['context' => 'klaro']),
        'class' => [
          'button-action',
          'button--small',
        ],
      ],
      '#button_type' => 'primary',
      '#limit_validation_errors' => [],
    ];
    $form['advanced']['callback_code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Callback code', [], ['context' => 'klaro']),
      '#description' => $this->t('This javascript will be executed after loading the page and every time a Klaro! dialog is saved. Available variables are <em>consent</em> and <em>service</em>. <em>consent</em> is either true or false and contains the current value but not whether there has been a change. <em>service</em> is the current service object.', [], ['context' => 'klaro']),
      '#default_value' => $app->callbackCode(),
      '#placeholder' => "console.log('User consent for service ' + service.name + ': consent=' + consent);\nif (consent == true) {\n  _my_call('consentGiven');\n} else {\n  _my_call('consentRevoked');\n}",
    ];
    $form['advanced']['files_wrapper'] = [
      '#type' => 'item',
      '#title' => $this->t('Sources', [], ['context' => 'klaro']),
      '#description' => $this->t('Klaro! needs to know which sources should be managed for this service, so it can automatically add the attributes required by klaro. These sources are re-enabled and loaded once the user gives consent for this service.', [], ['context' => 'klaro']),
      '#description_display' => 'before',
    ];
    $form['advanced']['files_wrapper']['js'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sources', [], ['context' => 'klaro']),
      '#description' => $this->t('As they appear in the src attribute of script, iframe, img, video and audio tags. Enter one source per line, partial matches are supported.', [], ['context' => 'klaro']),
      '#default_value' => implode("\n", $app->javascripts()),
      '#placeholder' => "modules/custom/mymodule/js/script.js\nhttps://example.org/script.js\ntracking.js\nservice.example.org",
    ];
    $form['advanced']['files_wrapper']['wrapper_identifier'] = [
      '#type' => 'textarea',
      '#title' => $this->t('QuerySelector of additional elements', [], ['context' => 'klaro']),
      '#description' => $this->t('Some embeds have additional markup that must be blocked. Enter the corresponding querySelector, one per line. Klaro! will add a wrapper around these elements and show a contextual consent dialog.<br><strong>Example:</strong> a Bluesky post has additional <code>&lt;blockquote class="bluesky-embed"&gt;</code>, so enter <code>.bluesky-embed</code>.', [], ['context' => 'klaro']),
      '#default_value' => implode("\n", $app->wrapperIdentifier()),
      '#placeholder' => ".geolocation-map-wrapper\n.twitter-tweet\n.bluesky-embed\n.g-recaptcha",
    ];
    $form['advanced']['files_wrapper']['att'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Attachments', [], ['context' => 'klaro']),
      '#description' => $this->t('Some Javascript files are added as <em>page attachments</em> with a unique identifier. If Klaro! should take control over these scripts, enter their IDs here, one per line.<br>See <a href="@website">this example</a> for <em>hello-world</em>.', ['@website' => 'https://www.drupal.org/node/2274843#entire-page'], ['context' => 'klaro']),
      '#default_value' => implode("\n", $app->attachments()),
      '#placeholder' => 'hello-world',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\klaro\KlaroAppInterface $app */
    $app = $this->entity;
    $cookies = $form_state->getValue('cookies');
    // Lets filter out action and empty cookie infos.
    $filtered_cookies = [];
    foreach ($cookies as $i => $info) {
      if (
        $i === 'action' ||
        (empty($info['regex']) && empty($info['path']) && empty($info['domain']))
      ) {
        continue;
      }
      $filtered_cookies[] = $info;
    }
    $app->setCookies($filtered_cookies);
    $app->setPurposes(array_keys(array_filter($form_state->getValue('purposes'))));
    $app->setCallbackCode($form_state->getValue('callback_code'));
    $app->setJavaScripts(array_filter(array_map('trim', explode("\n", $form_state->getValue('js')))));
    $app->setWrapperIdentifier(array_filter(array_map('trim', explode("\n", $form_state->getValue('wrapper_identifier')))));
    $app->setAttachments(array_filter(array_map('trim', explode("\n", $form_state->getValue('att')))));

    // @todo If JS or attachments changed, clear cache_factory.data cache.
    $status = $app->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The Klaro! service %label has been created.', [
        '%label' => $app->label(),
      ], ['context' => 'klaro']));
    }
    else {
      $this->messenger()->addMessage($this->t('The Klaro! service %label has been updated.', [
        '%label' => $app->label(),
      ], ['context' => 'klaro']));
    }

    $form_state->setRedirect('entity.klaro_app.collection');

    return $status;
  }

  /**
   * Helper function to check whether an Klaro! service entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('klaro_app')->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

  /**
   * Returns the cookies form elements.
   */
  public function returnCookies(array &$form, FormStateInterface $form_state) {
    return $form['advanced']['cookies_wrapper']['cookies'];
  }

  /**
   * Adds / removes cookies to / from the form_state.
   */
  public function ajaxCookieSubmit(array &$form, FormStateInterface $form_state) {
    $cookies = $form_state->get('cookies');
    $parents = $form_state->getTriggeringElement()['#parents'];

    switch ($parents[2]) {
      case 'add':
        $cookies[] = [
          'regex' => '',
          'path' => '',
          'domain' => '',
        ];
        break;

      case 'delete':
        unset($cookies[$parents[1]]);
        break;
    }

    $form_state->set('cookies', $cookies);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Form element validation for text with restricted tags.
   */
  public static function validateXss(&$element, FormStateInterface $form_state) {
    $value = trim($element['#value']);
    $allowed = ['a', 'strong', 'em'];
    $filtered = Xss::filter($value, $allowed);
    $form_state->setValueForElement($element, $filtered);
    if ($filtered != $value) {
      $form_state->setError($element, t('Only following tags are allowed: a, strong, em.'));
    }
  }

  /**
   * Form element validation handler replacement for #type 'url'.
   */
  public static function validateUrl(&$element, FormStateInterface $form_state) {
    $value = trim($element['#value']);
    $form_state->setValueForElement($element, $value);

    if ($value !== '' && !UrlHelper::isValid($value, TRUE)) {
      // Value is not a valid absolute url.
      try {
        // Make sure the uri looks sensible.
        $url = Url::fromUri($value);
        // Make sure we can actually build a link with this uri.
        $url->toString();
      }
      // @todo Provide translated messages for the various error states.
      catch (\InvalidArgumentException $e) {
        $form_state->setError($element, t('The URL %url is not valid: @error', [
          '%url' => $value,
          '@error' => $e->getMessage(),
        ]));
      }
      catch (RouteNotFoundException $e) {
        $form_state->setError($element, t('The specified route does not exist.'));
      }
    }
  }

}
