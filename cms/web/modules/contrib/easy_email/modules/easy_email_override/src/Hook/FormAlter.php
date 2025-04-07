<?php

namespace Drupal\easy_email_override\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\easy_email_override\Entity\EmailOverrideInterface;
use Drupal\Core\Hook\Attribute\Hook;

class FormAlter {

  use StringTranslationTrait;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  #[Hook('form_user_admin_settings_alter')]
  public function userAdminSettings(&$form, FormStateInterface $form_state, $form_id) {
    $override_map = [
      'status_activated' => 'email_activated',
      'status_blocked' => 'email_blocked',
      'status_canceled' => 'email_canceled',
      'cancel_confirm' => 'email_cancel_confirm',
      'register_pending_approval_admin' => 'email_pending_approval_admin',
      'register_pending_approval' => 'email_pending_approval',
      'register_admin_created' => 'email_admin_created',
      'register_no_approval_required' => 'email_no_approval_required',
      'password_reset' => 'email_password_reset',
    ];

    /** @var EmailOverrideInterface[] $email_overrides */
    $override_storage = \Drupal::entityTypeManager()->getStorage('easy_email_override');
    $results = $override_storage->getQuery()
      ->condition('module', 'user')
      ->condition('key', array_keys($override_map), 'IN')
      ->accessCheck(FALSE)
      ->execute();
    $email_overrides = $override_storage->loadMultiple($results);
    foreach ($email_overrides as $override) {
      $form_key = $override_map[$override->getKey()] ?? NULL;
      if ($form_key !== NULL && !empty($form[$form_key])) {
        foreach (Element::children($form[$form_key]) as $sub_key) {
          $form[$form_key][$sub_key]['#access'] = FALSE;
        }
        $form[$form_key]['easy_email'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('This email template has been overridden by Easy Email. <a href=":template">Click here to edit the Easy Email template.</a>', [
            ':template' => Url::fromRoute('entity.easy_email_type.edit_form', ['easy_email_type' => $override->getEasyEmailType()])->toString(),
          ]),
          '#attributes' => [
            'class' => ['messages', 'messages--status'],
          ],
        ];
      }
    }
  }
}
