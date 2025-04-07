<?php

namespace Drupal\easy_email\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EasyEmailTypeForm.
 */
class EasyEmailTypeForm extends EntityForm {

  protected const DEFAULT_TOKEN_DEPTH = 4;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * EasyEmailTypeForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $formDisplay = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load(sprintf('easy_email.%s.default', $this->entity->id()));

    /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface $easy_email_type */
    $easy_email_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $easy_email_type->label(),
      '#description' => $this->t("Label for the Email type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $easy_email_type->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => '\Drupal\easy_email\Entity\EasyEmailType::load',
      ],
      '#disabled' => !$easy_email_type->isNew(),
    ];

    if ($easy_email_type->isNew()) {
      return $form;
    }

    /** @var \Drupal\easy_email\Entity\EasyEmailInterface $easy_email */
    $easy_email = $this->entityTypeManager->getStorage('easy_email')->create([
      'type' => $easy_email_type->id(),
    ]);

    if ($easy_email->hasField('key')) {
      $form['key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Unique key pattern'),
        '#maxlength' => 255,
        '#default_value' => $easy_email_type->getKey(),
        '#description' => $this->t("To prevent duplicate emails, use tokens to define a key that uniquely identifies a specific email. If duplicates are allowed, you can leave this blank."),
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('key')),
      ];
    }

    $form['to'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recipients'),
      '#access' => ($formDisplay === NULL || $formDisplay->getComponent('recipient_address')
        || $formDisplay->getComponent('cc_address')
        || $formDisplay->getComponent('bcc_address')),
    ];

    $form['to']['recipient'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To'),
      '#maxlength' => 1024,
      '#default_value' => !empty($easy_email_type->getRecipient()) ? implode(', ', $easy_email_type->getRecipient()) : NULL,
      '#access' => ($formDisplay === NULL || $formDisplay->getComponent('recipient_address')),
    ];

    if ($easy_email->hasField('cc_address')) {
      $form['to']['cc'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CC'),
        '#maxlength' => 1024,
        '#default_value' => !empty($easy_email_type->getCc()) ? implode(', ', $easy_email_type->getCc()) : NULL,
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('cc_address')),
      ];
    }

    if ($easy_email->hasField('bcc_address')) {
      $form['to']['bcc'] = [
        '#type' => 'textfield',
        '#title' => $this->t('BCC'),
        '#maxlength' => 1024,
        '#default_value' => !empty($easy_email_type->getBcc()) ? implode(', ', $easy_email_type->getBcc()) : NULL,
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('bcc_address')),
      ];
    }

    if ($easy_email->hasField('from_name') || $easy_email->hasField('from_address') || $easy_email->hasField('reply_to')) {
      $form['sender'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Sender'),
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('from_name')
          || $formDisplay->getComponent('from_address')
          || $formDisplay->getComponent('reply_to')),
      ];

      if ($easy_email->hasField('from_name')) {
        $form['sender']['fromName'] = [
          '#type' => 'textfield',
          '#title' => $this->t('From name'),
          '#maxlength' => 255,
          '#default_value' => $easy_email_type->getFromName(),
          '#access' => ($formDisplay === NULL || $formDisplay->getComponent('from_name')),
        ];
      }

      if ($easy_email->hasField('from_address')) {
        $form['sender']['fromAddress'] = [
          '#type' => 'textfield',
          '#title' => $this->t('From address'),
          '#maxlength' => 255,
          '#default_value' => $easy_email_type->getFromAddress(),
          '#access' => ($formDisplay === NULL || $formDisplay->getComponent('from_address')),
        ];
      }

      if ($easy_email->hasField('reply_to')) {
        $form['sender']['replyToAddress'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Reply to address'),
          '#maxlength' => 255,
          '#default_value' => $easy_email_type->getReplyToAddress(),
          '#access' => ($formDisplay === NULL || $formDisplay->getComponent('reply_to')),
        ];
      }
    }

    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content'),
    ];

    $form['content']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#default_value' => $easy_email_type->getSubject(),
      '#access' => ($formDisplay === NULL || $formDisplay->getComponent('subject')),
    ];

    $form['content']['body'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-body-html',
    ];

    if ($easy_email->hasField('body_html')) {
      $form['body_html'] = [
        '#type' => 'details',
        '#title' => $this->t('HTML body'),
        '#group' => 'body',
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('body_html')
          || $formDisplay->getComponent('inbox_preview'))
      ];
      $body_html = $easy_email_type->getBodyHtml();
      $allowed_formats = $easy_email->getFieldDefinition('body_html')
        ->getSetting('allowed_formats');
      if (empty($allowed_formats)) {
        $allowed_formats = NULL;
      }

      $form['body_html']['bodyHtml'] = [
        '#type' => 'text_format',
        '#rows' => 30,
        '#title' => $this->t('HTML body'),
        '#default_value' => !empty($body_html) ? $body_html['value'] : NULL,
        '#format' => !empty($body_html) ? $body_html['format'] : NULL,
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('body_html')),
        '#allowed_formats' => $allowed_formats,
      ];

    }

    if ($easy_email->hasField('body_html') && $easy_email->hasField('inbox_preview')) {
      $form['body_html']['inboxPreview'] = [
        '#type' => 'textarea',
        '#description' => $this->t('The inbox preview text will be hidden in the body of the message. It will only be seen while viewing a message in the inbox of supported email clients.'),
        '#rows' => 5,
        '#title' => $this->t('Inbox preview'),
        '#default_value' => $easy_email_type->getInboxPreview(),
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('inbox_preview')),
      ];
    }

    if ($easy_email->hasField('body_plain')) {
      $form['body_plain'] = [
        '#type' => 'details',
        '#title' => $this->t('Plain text body'),
        '#group' => 'body',
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('body_plain')),
      ];

      if ($easy_email->hasField('body_html')) {
        $form['body_plain']['generateBodyPlain'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Generate plain text body from HTML body'),
          '#default_value' => $easy_email_type->getGenerateBodyPlain(),
        ];
      }

      $form['body_plain']['bodyPlain'] = [
        '#type' => 'textarea',
        '#rows' => 30,
        '#title' => $this->t('Plain text body'),
        '#default_value' => $easy_email_type->getBodyPlain(),
        '#states' => [
          'disabled' => [
            ':input[name="generateBodyPlain"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    if ($easy_email->hasField('attachment_path')) {
      $form['content']['attachment'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Attachments'),
        '#maxlength' => 1024,
        '#description' => $this->t('Use relative file paths, URIs, and tokens that resolve to file paths. Separate multiple paths with a comma.'),
        '#default_value' => !empty($easy_email_type->getAttachment()) ? implode(', ', $easy_email_type->getAttachment()) : NULL,
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('attachment_path')),
      ];
    }


    if ($easy_email->hasField('attachment')) {
      $form['content']['saveAttachment'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Save attachments to email log'),
        '#default_value' => $easy_email_type->getSaveAttachment(),
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('attachment')),
      ];
      /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
      $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
      $scheme_options = $stream_wrapper_manager->getNames(StreamWrapperInterface::WRITE_VISIBLE);

      // Default to private scheme is none has been chosen before.
      $default_scheme = $easy_email_type->getAttachmentScheme();
      if (empty($default_scheme) && !empty($scheme_options['private'])) {
        $default_scheme = 'private';
      }
      elseif (empty($default_scheme) && !empty($scheme_options['public'])) {
        $default_scheme = 'public';
      }

      $form['content']['attachmentScheme'] = [
        '#type' => 'radios',
        '#options' => $scheme_options,
        '#title' => $this->t('Upload destination'),
        '#default_value' => $default_scheme,
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="saveAttachment"]' => ['checked' => TRUE],
          ],
        ],
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('attachment')),
      ];

      $form['content']['attachmentDirectory'] = [
        '#type' => 'textfield',
        '#title' => $this->t('File directory'),
        '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes. This field supports tokens.'),
        '#default_value' => $easy_email_type->getAttachmentDirectory(),
        '#states' => [
          'visible' => [
            ':input[name="saveAttachment"]' => ['checked' => TRUE],
          ],
        ],
        '#access' => ($formDisplay === NULL || $formDisplay->getComponent('attachment')),
      ];
    }

    $form['tokens'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Replacement patterns'),
    ];

    $form['tokens']['tree_link'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['easy_email'],
      '#show_restricted' => TRUE,
      '#recursion_limit' => $form_state->getValue('depth') ?? self::DEFAULT_TOKEN_DEPTH,
      '#prefix' => '<div id="token-tree-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['tokens']['depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Token tree maximum depth'),
      '#description' => $this->t('Increase this value to access more deeply nested tokens,
        but be aware that increasing this too much may prevent the token browser dialog from loading.'),
      '#default_value' => $form_state->getValue('depth') ?? self::DEFAULT_TOKEN_DEPTH,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateTokenTreeRecursionLimit',
        'wrapper' => 'token-tree-wrapper',
        'disable-refocus' => TRUE,
      ],
    ];

    $form['email_storage'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email storage'),
    ];

    $form['email_storage']['saveEmail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save emails of this type by default'),
      '#default_value' => $easy_email_type->getSaveEmail(),
    ];

    $form['email_storage']['allowSavingEmail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow emails of this type to be saved'),
      '#default_value' => $easy_email_type->getAllowSavingEmail(),
      '#description' => $this->t('This will have no effect if saving emails by default is checked.'),
    ];

    $form['email_storage']['delete'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Automatic deletion settings'),
    ];

    $form['email_storage']['delete']['purgeEmails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete emails of this type automatically'),
      '#default_value' => $easy_email_type->getPurgeEmails(),
    ];

    $form['email_storage']['delete']['purgeInterval'] = [
      '#type' => 'number',
      '#step' => 1,
      '#min' => 0,
      '#field_suffix' => $this->t(' days after sending'),
      '#title' => $this->t('Delete emails'),
      '#default_value' => $easy_email_type->getPurgeInterval(),
      '#states' => [
        'visible' => [
          ':input[name="purgeEmails"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="purgeEmails"]' => ['checked' => TRUE],
        ],
      ]
    ];

    $form['email_storage']['delete']['purgePeriod'] = [
      '#type' => 'value',
      '#value' => 'days',
    ];

    return $form;
  }

  /**
   * Ajax callback to update the token tree.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function updateTokenTreeRecursionLimit(array &$form, FormStateInterface $form_state) {
    return $form['tokens']['tree_link'];
  }

  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (empty($values['purgeInterval'])) {
      $values['purgeInterval'] = NULL;
    }
    if ($this->entity instanceof EntityWithPluginCollectionInterface) {
      // Do not manually update values represented by plugin collections.
      $values = array_diff_key($values, $this->entity->getPluginCollections());
    }

    // @todo This relies on a method that only exists for config and content
    //   entities, in a different way. Consider moving this logic to a config
    //   entity specific implementation.
    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface $easy_email_type */
    $easy_email_type = $this->entity;

    if ($easy_email_type->isNew()) {
      $easy_email_type->save();
      $form_state->setRedirect('entity.easy_email_type.edit_form', ['easy_email_type' => $easy_email_type->id()]);
      $this->messenger->addMessage($this->t('Created the %label Email type. You may now edit the template below.', [
        '%label' => $easy_email_type->label(),
      ]));
    }
    else {
      $easy_email_type->setRecipient($this->explodeAndTrim($form_state->getValue('recipient')))
        ->setCc($this->explodeAndTrim($form_state->getValue('cc')))
        ->setBcc($this->explodeAndTrim($form_state->getValue('bcc')))
        ->setAttachment($this->explodeAndTrim($form_state->getValue('attachment')))
        ->setSaveEmail((bool) $form_state->getValue('saveEmail'))
        ->setAllowSavingEmail((bool) $form_state->getValue('allowSavingEmail'));

      if (!empty($form_state->getValue('purgeEmails'))) {
        $easy_email_type->setPurgeEmails(TRUE)
          ->setPurgeInterval((int) $form_state->getValue('purgeInterval'))
          ->setPurgePeriod($form_state->getValue('purgePeriod'));
      }
      else {
        $easy_email_type->setPurgeEmails(FALSE)
          ->setPurgeInterval(NULL)
          ->setPurgePeriod(NULL);
      }

      $easy_email_type->save();
      $this->messenger->addMessage($this->t('Saved the %label Email type.', [
        '%label' => $easy_email_type->label(),
      ]));
      $form_state->setRedirectUrl($easy_email_type->toUrl('collection'));
    }

  }

  /**
   * @param string $string
   * @param string $delimiter
   *
   * @return array
   */
  protected function explodeAndTrim($string, $delimiter = ',') {
    $return = [];
    if (!empty($string)) {
      $return = explode($delimiter, $string);
      $return = array_filter(array_map('trim', $return));
    }
    return $return;
  }

}
